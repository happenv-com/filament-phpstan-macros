<?php

declare(strict_types=1);

namespace Happenv\FilamentPhpstanMacros\Tests\Types\Data;

use Filament\Forms\Components\TextInput;

use function PHPStan\Testing\assertType;

$input = TextInput::make('name');

// Parameters and return type come from the registered closure.
assertType('string', $input->fixtureLabelLength(3));

// A fluent macro typed to return an ancestor keeps the caller's type…
assertType(TextInput::class, $input->fixtureFluent());

// …so methods only the subclass has stay visible after it.
assertType(TextInput::class, $input->fixtureFluent()->maxLength(10));

// A macro registered as an invokable object.
assertType('string', $input->fixtureInvokable('abc', uppercase: true));

// The macro registered on the class itself wins over its ancestor's.
assertType('float', $input->fixtureOverridden());

// A macro registered through mixin().
assertType('int', $input->fixtureFromMixin(2));

// Without a declared return type the result is mixed — type your macros.
assertType('mixed', $input->fixtureUntyped());
