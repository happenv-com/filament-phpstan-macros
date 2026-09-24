<?php

declare(strict_types=1);

namespace Happenv\FilamentPhpstanMacros;

use Closure;
use Filament\Support\Concerns\Macroable;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\MethodsClassReflectionExtension;
use PHPStan\Type\ClosureTypeFactory;
use PHPStan\Type\StaticType;
use PHPStan\Type\Type;
use ReflectionException;

/**
 * Teaches PHPStan about macros registered on Filament components (Column,
 * TextEntry, Grid, TextInput, …).
 *
 * Filament ships its OWN macro trait — Filament\Support\Concerns\Macroable —
 * instead of Laravel's Illuminate\Support\Traits\Macroable. Larastan's macro
 * extension only recognises Laravel's trait, so it never sees Filament macros:
 * they are reported as "undefined method", and ignoring those errors also
 * blinds PHPStan to everything chained AFTER the macro
 * (e.g. `->numericSortable()->disabled()`).
 *
 * This extension mirrors Larastan's logic for Filament's trait: it reads the
 * registered macro and derives its real parameters and return type via
 * PHPStan's ClosureTypeFactory, so a macro call is analysed exactly like a
 * native method. Fluent macros additionally keep the caller's concrete type —
 * see resolveReturnType().
 *
 * Macros are read from the static Macroable::$macros at analysis time, so they
 * must be registered by then: Larastan boots the Laravel application, which runs
 * the service providers that register them.
 */
final class FilamentMacroMethodsClassReflectionExtension implements MethodsClassReflectionExtension
{
    /**
     * By name, not `::class`: the package does not depend on Filament — it only
     * analyses code that does.
     */
    private const string MACROABLE_TRAIT = Macroable::class;

    /** @var array<string, MethodReflection> */
    private array $cache = [];

    public function __construct(
        private readonly ClosureTypeFactory $closureTypeFactory,
    ) {}

    public function hasMethod(ClassReflection $classReflection, string $methodName): bool
    {
        $cacheKey = $classReflection->getName() . '::' . $methodName;

        if (array_key_exists($cacheKey, $this->cache)) {
            return true;
        }

        // Only Filament's own Macroable trait — Laravel's is Larastan's job.
        // getTraits(true) walks parents, since components inherit the trait from
        // Filament\Support\Components\Component rather than using it directly.
        if (! array_key_exists(self::MACROABLE_TRAIT, $classReflection->getTraits(true))) {
            return false;
        }

        $macro = $this->resolveMacroClosure($classReflection, $methodName);

        if (! $macro instanceof Closure) {
            return false;
        }

        $closureType = $this->closureTypeFactory->fromClosureObject($macro);

        $this->cache[$cacheKey] = new FilamentMacroMethodReflection(
            $classReflection,
            $methodName,
            $closureType,
            $this->resolveReturnType($closureType->getReturnType(), $classReflection),
        );

        return true;
    }

    /**
     * Preserve the caller's concrete type for fluent macros. A macro typed to
     * return one of the caller's own ancestors (e.g. `Column` from a method
     * called on `TextColumn`) returns `$this` for chaining, so resolve it to
     * `static` — otherwise the chain would collapse to the base type and hide
     * subclass-only methods (`->numericSortable()->numeric()`). Any other
     * return type is kept verbatim.
     */
    private function resolveReturnType(Type $declaredReturnType, ClassReflection $classReflection): Type
    {
        $ancestry = $this->classHierarchy($classReflection);

        foreach ($declaredReturnType->getObjectClassNames() as $returnedClass) {
            if (in_array($returnedClass, $ancestry, strict: true)) {
                return new StaticType($classReflection);
            }
        }

        return $declaredReturnType;
    }

    public function getMethod(ClassReflection $classReflection, string $methodName): MethodReflection
    {
        $cacheKey = $classReflection->getName() . '::' . $methodName;

        if (! array_key_exists($cacheKey, $this->cache)) {
            $this->hasMethod($classReflection, $methodName);
        }

        return $this->cache[$cacheKey];
    }

    /**
     * Resolve the macro for $methodName the way Filament's Macroable does:
     * Macroable::$macros is keyed `[name][registeringClass]`, so prefer the
     * macro registered on the class itself, then walk up its parents.
     */
    private function resolveMacroClosure(ClassReflection $classReflection, string $methodName): ?Closure
    {
        $macros = $this->registeredMacros($classReflection);

        if (! array_key_exists($methodName, $macros) || ! is_array($macros[$methodName])) {
            return null;
        }

        $byRegisteringClass = $macros[$methodName];

        foreach ($this->classHierarchy($classReflection) as $candidate) {
            $macro = $byRegisteringClass[$candidate] ?? null;

            if ($macro instanceof Closure) {
                return $macro;
            }

            // Filament's macro() accepts any callable (an invokable object, an
            // array callable); analyse it through its closure form.
            if (is_callable($macro)) {
                return Closure::fromCallable($macro);
            }
        }

        return null;
    }

    /**
     * Read the runtime value of the static `$macros` property. Requires the app
     * to have been booted so the macros are registered.
     *
     * @return array<mixed> keyed by macro name, then by the registering class
     */
    private function registeredMacros(ClassReflection $classReflection): array
    {
        $nativeReflection = $classReflection->getNativeReflection();

        if (! $nativeReflection->hasProperty('macros')) {
            return [];
        }

        try {
            $value = $nativeReflection->getProperty('macros')->getValue();
        } catch (ReflectionException) {
            return [];
        }

        return is_array($value) ? $value : [];
    }

    /**
     * The class and its ancestors, nearest first — mirrors how Filament's
     * getMacro() resolves `static::class` then `class_parents()`.
     *
     * @return list<string>
     */
    private function classHierarchy(ClassReflection $classReflection): array
    {
        $names = [$classReflection->getName()];

        $parent = $classReflection->getParentClass();

        while ($parent instanceof ClassReflection) {
            $names[] = $parent->getName();
            $parent = $parent->getParentClass();
        }

        return $names;
    }
}
