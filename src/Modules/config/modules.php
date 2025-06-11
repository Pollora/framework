<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Module Auto-Discovery
    |--------------------------------------------------------------------------
    |
    | This controls which features of modules should be automatically discovered.
    |
    */
    'auto-discover' => [
        'migrations' => true,
        'translations' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Module Paths
    |--------------------------------------------------------------------------
    |
    | Here you can specify the paths where modules are located.
    |
    */
    'paths' => [
        'modules' => function_exists('get_theme_root') ? get_theme_root() : base_path('themes'), // Use WordPress theme directory
        'assets' => 'assets',
        'config' => 'config',
        'database' => 'database',
        'migrations' => 'database/migrations',
        'factories' => 'database/factories',
        'seeders' => 'database/seeders',
        'lang' => 'languages',
        'views' => 'views',
    ],

    /*
    |--------------------------------------------------------------------------
    | Module Stubs
    |--------------------------------------------------------------------------
    |
    | Stub files configuration for module generation.
    |
    */
    'stubs' => [
        'enabled' => false,
        'path' => base_path('stubs'),
    ],
];
