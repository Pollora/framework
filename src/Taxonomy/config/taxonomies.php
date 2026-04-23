<?php

declare(strict_types=1);
use Pollora\Taxonomy\Application\Services\TaxonomyService;

return [
    /*
    |--------------------------------------------------------------------------
    | Taxonomies Directory
    |--------------------------------------------------------------------------
    |
    | This value determines the directory where the taxonomy classes are stored.
    | By default, this is set to 'Cms/Taxonomies' within the application directory.
    |
    */
    'directory' => 'Cms/Taxonomies',

    /*
    |--------------------------------------------------------------------------
    | Taxonomies Service Provider
    |--------------------------------------------------------------------------
    |
    | This value determines the service provider class that will be used to
    | register taxonomies with WordPress. You can extend or replace this
    | with your own implementation if needed.
    |
    */
    'provider' => TaxonomyService::class,
];