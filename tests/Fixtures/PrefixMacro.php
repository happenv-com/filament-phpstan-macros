<?php

declare(strict_types=1);

namespace Happenv\FilamentPhpstanMacros\Tests\Fixtures;

/**
 * A macro registered as an invokable object rather than a Closure.
 */
final class PrefixMacro
{
    public function __invoke(string $prefix, bool $uppercase = false): string
    {
        return $uppercase ? strtoupper($prefix) : $prefix;
    }
}
