<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Skipped Classes
    |--------------------------------------------------------------------------
    |
    | Fully qualified class names that should be excluded from discovery.
    | These classes will not be processed by any discovery service,
    | regardless of their attributes.
    |
    | Useful for excluding third-party classes or base classes that should
    | not be auto-registered without modifying their source code.
    |
    | Example:
    |   'skip_classes' => [
    |       App\Legacy\OldController::class,
    |       SomeVendor\Package\InternalHelper::class,
    |   ],
    |
    */

    'skip_classes' => [],

    /*
    |--------------------------------------------------------------------------
    | Skipped Paths
    |--------------------------------------------------------------------------
    |
    | File path patterns that should be excluded from discovery scanning.
    | Any PHP file whose path contains one of these strings will be skipped.
    |
    | Paths are matched with str_contains() — use partial paths or directory
    | names to exclude entire folders.
    |
    | Example:
    |   'skip_paths' => [
    |       '/Fixtures/',
    |       '/Tests/',
    |       '/stubs/',
    |   ],
    |
    */

    'skip_paths' => [],

];
