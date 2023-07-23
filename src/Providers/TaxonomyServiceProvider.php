<?php

declare(strict_types=1);

namespace Pollen\Providers;

use Illuminate\Support\ServiceProvider;
use Pollen\Services\Translater;
use Pollen\Support\Facades\Action;

class TaxonomyServiceProvider extends ServiceProvider
{
    public function register()
    {
            Action::add('init', [$this, 'registerTaxonomies'], 1);
    }

    /**
     * Register all the site's taxonomies
     *
     * @return void
     */
    public function registerTaxonomies()
    {
        // Get the post types from the config.
        $taxonomies = config('taxonomies');

        $translater = new Translater($taxonomies, 'taxonomies');
        $taxonomies = $translater->translate([
            '*.labels.*',
            '*.names.singular',
            '*.names.plural',
        ]);

        // Iterate over each post type.
        collect($taxonomies)->each(function ($args, $key) {

            // Check if names are set, if not keep it as an empty array
            $links = $args['links'] ?? [];

            // Unset names from item
            unset($args['links']);

            // Check if names are set, if not keep it as an empty array
            $names = $args['names'] ?? [];

            // Unset names from item
            unset($args['names']);

            // Register the extended post type.
            register_extended_taxonomy($key, $links, $args, $names);
        });
    }
}
