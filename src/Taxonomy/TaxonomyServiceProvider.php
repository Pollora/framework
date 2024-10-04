<?php

declare(strict_types=1);

/**
 * Class PostTypeServiceProvider
 */

namespace Pollen\Taxonomy;

use Illuminate\Support\ServiceProvider;
use Pollen\Support\Facades\Taxonomy;

/**
 * Class PostTypeServiceProvider
 *
 * A service provider for registering custom post types.
 */
class TaxonomyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind('wp.taxonomy', fn($app): \Pollen\Taxonomy\TaxonomyFactory => new TaxonomyFactory($app));

        $this->registerTaxonomies();
    }

    /**
     * Register all the site's custom post types
     */
    public function registerTaxonomies(): void
    {
        // Get the post types from the config.
        $taxonomies = config('taxonomies');

        // Iterate over each post type.
        collect($taxonomies)->each(function ($args, $key): void {
            // Register the extended post type.
            $links = $args['links'] ?? [];
            $singular = $args['names']['singular'] ?? null;
            $plural = $args['names']['plural'] ?? null;
            $slug = $args['names']['slug'] ?? null;
            Taxonomy::make($key, $links, $singular, $plural)->setSlug($slug)->setRawArgs($args);
        });
    }
}
