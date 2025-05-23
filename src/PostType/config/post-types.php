<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Post Types Directory
    |--------------------------------------------------------------------------
    |
    | This value determines the directory where the post type classes are stored.
    | By default, this is set to 'Cms/PostTypes' within the application directory.
    |
    */
    'directory' => 'Cms/PostTypes',

    /*
    |--------------------------------------------------------------------------
    | Post Types Service Provider
    |--------------------------------------------------------------------------
    |
    | This value determines the service provider class that will be used to
    | register post types with WordPress. You can extend or replace this
    | with your own implementation if needed.
    |
    */
    'provider' => Pollora\PostType\Infrastructure\Providers\PostTypeServiceProvider::class,
];
