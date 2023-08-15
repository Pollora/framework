<?php

declare(strict_types=1);

/**
 * Class PostTypeServiceProvider
 */

namespace Pollen\Taxonomy;

use Illuminate\Support\ServiceProvider;
use Pollen\Support\Facades\Action;
use Pollen\Support\Facades\Taxonomy;

/**
 * Class PostTypeServiceProvider
 *
 * A service provider for registering custom post types.
 */
class TaxonomyServiceProvider extends ServiceProvider
{
    public function register()
    {
        Action::add('init', [$this, 'registerTaxonomies'], 1);

        $this->app->bind('taxonomy', function ($app) {
            return new TaxonomyFactory($app);
        });
    }

    /**
     * Register all the site's custom post types
     *
     * @return void
     */
    public function registerTaxonomies()
    {
        // Get the post types from the config.
        $taxonomies = config('taxonomies');

        // Iterate over each post type.
        collect($taxonomies)->each(function ($args, $key) {
            // Register the extended post type.
            $links = $args['links'] ?? [];
            $singular = $args['names']['singular'] ?? null;
            $plural = $args['names']['plural'] ?? null;
            $slug = $args['names']['slug'] ?? null;
            Taxonomy::make($key, $links, $singular, $plural)->setSlug($slug)->setRawArgs($args);
        });
    }
}
