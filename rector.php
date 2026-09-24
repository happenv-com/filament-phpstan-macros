<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

/*
 * Library, not an application: no privatization and no "treat classes as
 * final" here. Rector cannot see code that extends or calls the package, so
 * narrowing visibility or finalizing classes could break it without a failing
 * test in this repository.
 */
return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    // Fixtures PHPStan analyses: their exact shape (e.g. a macro WITHOUT a
    // return type) is what the tests check.
    ->withSkip([
        __DIR__ . '/tests/Types/data',
        __DIR__ . '/tests/bootstrap.php',
    ])
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
        earlyReturn: true,
    )
    ->withPhpSets();
