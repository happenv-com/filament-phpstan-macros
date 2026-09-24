<?php

declare(strict_types=1);

use Filament\Forms\Components\Field;
use Filament\Forms\Components\TextInput;
use Happenv\FilamentPhpstanMacros\Tests\Fixtures\PrefixMacro;

require __DIR__ . '/../vendor/autoload.php';

/*
 * The macros tests/Types/data/macros.php calls. In an application they are
 * registered by a service provider and Larastan boots the app before analysis;
 * here they are registered before PHPStan reads Macroable::$macros.
 */

// A scalar return type is kept as it is.
TextInput::macro('fixtureLabelLength', fn (int $factor): string => str_repeat('x', $factor));

// Registered on an ancestor and typed to return it: a fluent macro, so a call
// on TextInput must keep returning TextInput for chaining.
Field::macro('fixtureFluent', fn (): Field => TextInput::make('fluent'));

// Any callable, not only a Closure.
TextInput::macro('fixtureInvokable', new PrefixMacro);

// Registered on the concrete class: wins over the one on its ancestor.
Field::macro('fixtureOverridden', fn (): int => 1);
TextInput::macro('fixtureOverridden', fn (): float => 1.5);

// Registered through mixin(): each method returns the macro.
TextInput::mixin(new class
{
    public function fixtureFromMixin(): Closure
    {
        return fn (int $times): int => $times * 2;
    }
});

// No declared return type: analysed as mixed, as Larastan does for Laravel macros.
TextInput::macro('fixtureUntyped', fn () => 'x');
