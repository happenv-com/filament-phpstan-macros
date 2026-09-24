<?php

declare(strict_types=1);

namespace Happenv\FilamentPhpstanMacros\Tests\Unit;

use Happenv\FilamentPhpstanMacros\FilamentMacroMethodReflection;
use PHPStan\Testing\PHPStanTestCase;
use PHPStan\TrinaryLogic;
use PHPStan\Type\ClosureType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\StringType;
use stdClass;

final class FilamentMacroMethodReflectionTest extends PHPStanTestCase
{
    /** @return array<string> */
    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/../../extension.neon'];
    }

    private function makeReflection(
        string $name = 'numericSortable',
        ?ClosureType $closureType = null,
        ?ObjectType $returnType = null,
    ): FilamentMacroMethodReflection {
        $declaringClass = self::createReflectionProvider()->getClass(stdClass::class);

        return new FilamentMacroMethodReflection(
            $declaringClass,
            $name,
            $closureType ?? new ClosureType([], new StringType, false),
            $returnType ?? new ObjectType(stdClass::class),
        );
    }

    public function test_basic_accessors(): void
    {
        $reflection = $this->makeReflection(name: 'myMacro');

        expect($reflection->getName())
            ->toBe('myMacro')
            ->and($reflection->getDeclaringClass()
                ->getName())
            ->toBe(stdClass::class)
            ->and($reflection->isStatic())
            ->toBeFalse()
            ->and($reflection->isPrivate())
            ->toBeFalse()
            ->and($reflection->isPublic())
            ->toBeTrue()
            ->and($reflection->getDocComment())
            ->toBeNull()
            ->and($reflection->getThrowType())
            ->toBeNull();
    }

    public function test_prototype_returns_itself(): void
    {
        $reflection = $this->makeReflection();

        expect($reflection->getPrototype())
            ->toBe($reflection);
    }

    public function test_trinary_flags(): void
    {
        $reflection = $this->makeReflection();

        expect($reflection->isDeprecated()->equals(TrinaryLogic::createNo()))
            ->toBeTrue()
            ->and($reflection->getDeprecatedDescription())
            ->toBeNull()
            ->and($reflection->isFinal()
                ->equals(TrinaryLogic::createNo()))
            ->toBeTrue()
            ->and($reflection->isInternal()
                ->equals(TrinaryLogic::createNo()))
            ->toBeTrue()
            ->and($reflection->hasSideEffects()
                ->equals(TrinaryLogic::createMaybe()))
            ->toBeTrue();
    }

    public function test_variant_carries_closure_parameters_and_return_type(): void
    {
        $returnType = new ObjectType(stdClass::class);
        $reflection = $this->makeReflection(
            closureType: new ClosureType([], new IntegerType, false),
            returnType: $returnType,
        );

        $variants = $reflection->getVariants();

        expect($variants)
            ->toHaveCount(1);
        // The single variant must expose the return type the extension computed.
        expect($variants[0]->getReturnType()->equals($returnType))
            ->toBeTrue();
        expect($variants[0]->isVariadic())
            ->toBeFalse();
    }

    public function test_variant_propagates_variadic_flag_from_closure(): void
    {
        $reflection = $this->makeReflection(
            closureType: new ClosureType([], new StringType, true),
        );

        expect($reflection->getVariants()[0]->isVariadic())
            ->toBeTrue();
    }
}
