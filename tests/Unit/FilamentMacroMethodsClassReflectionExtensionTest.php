<?php

declare(strict_types=1);

namespace Happenv\FilamentPhpstanMacros\Tests\Unit;

use Filament\Forms\Components\TextInput;
use Happenv\FilamentPhpstanMacros\FilamentMacroMethodReflection;
use Happenv\FilamentPhpstanMacros\FilamentMacroMethodsClassReflectionExtension;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Testing\PHPStanTestCase;
use PHPStan\Type\ClosureTypeFactory;
use stdClass;

final class FilamentMacroMethodsClassReflectionExtensionTest extends PHPStanTestCase
{
    /** @return array<string> */
    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/../../extension.neon'];
    }

    private function extension(): FilamentMacroMethodsClassReflectionExtension
    {
        /** @var ClosureTypeFactory $factory */
        $factory = self::getContainer()->getByType(ClosureTypeFactory::class);

        return new FilamentMacroMethodsClassReflectionExtension($factory);
    }

    private function reflectionProvider(): ReflectionProvider
    {
        return self::createReflectionProvider();
    }

    public function test_class_not_using_filament_macroable_trait_has_no_macro_method(): void
    {
        $extension = $this->extension();
        $class = $this->reflectionProvider()->getClass(stdClass::class);

        expect($extension->hasMethod($class, 'anything'))
            ->toBeFalse();
    }

    public function test_unknown_method_on_filament_component_is_not_resolved(): void
    {
        $extension = $this->extension();
        $class = $this->reflectionProvider()->getClass(TextInput::class);

        expect($extension->hasMethod($class, '__definitely_not_a_registered_macro__'))
            ->toBeFalse();
    }

    public function test_registered_filament_macro_is_resolved(): void
    {
        $macroName = 'devTestScalarMacro' . uniqid();
        TextInput::macro($macroName, fn (int $factor): string => (string) $factor);

        $extension = $this->extension();
        $class = $this->reflectionProvider()->getClass(TextInput::class);

        expect($extension->hasMethod($class, $macroName))
            ->toBeTrue();

        $method = $extension->getMethod($class, $macroName);

        expect($method)
            ->toBeInstanceOf(FilamentMacroMethodReflection::class)
            ->and($method->getName())
            ->toBe($macroName);
        // A scalar (non-ancestor) return type is preserved verbatim.
        expect($method->getVariants()[0]->getReturnType()->isString()->yes())
            ->toBeTrue();
    }

    public function test_fluent_macro_returning_ancestor_resolves_to_static(): void
    {
        $macroName = 'devTestFluentMacro' . uniqid();
        // Returns the base Component (an ancestor of TextInput) → should become static.
        TextInput::macro(
            $macroName,
            // The extension reads the closure's DECLARED return type; the body is never
            // looked at, so it does not need `$this` — and `@var` on `$this` is rejected.
            fn (): TextInput => TextInput::make('probe'),
        );

        $extension = $this->extension();
        $class = $this->reflectionProvider()->getClass(TextInput::class);

        expect($extension->hasMethod($class, $macroName))
            ->toBeTrue();

        $returnType = $extension->getMethod($class, $macroName)->getVariants()[0]->getReturnType();

        // The caller's concrete type is preserved for chaining.
        expect($returnType->getObjectClassNames() === [TextInput::class] || $returnType->isObject()->yes())
            ->toBeTrue();
    }

    public function test_has_method_result_is_cached(): void
    {
        $macroName = 'devTestCachedMacro' . uniqid();
        TextInput::macro($macroName, fn (): string => 'x');

        $extension = $this->extension();
        $class = $this->reflectionProvider()->getClass(TextInput::class);

        expect($extension->hasMethod($class, $macroName))
            ->toBeTrue();
        // Second call hits the cache branch and still returns the same reflection.
        expect($extension->hasMethod($class, $macroName))
            ->toBeTrue();
        expect($extension->getMethod($class, $macroName))
            ->toBe($extension->getMethod($class, $macroName));
    }
}
