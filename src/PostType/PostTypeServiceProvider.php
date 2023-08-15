<?php

declare(strict_types=1);

/**
 * Class PostTypeServiceProvider
 */

namespace Pollen\PostType;

use Illuminate\Support\ServiceProvider;
use Pollen\Support\Facades\PostType;

/**
 * Class PostTypeServiceProvider
 *
 * A service provider for registering custom post types.
 */
class PostTypeServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->bind('posttype', function ($app) {
            return new PostTypeFactory($app);
        });
        $this->registerPostTypes();
    }

    /**
     * Register all the site's custom post types
     *
     * @return void
     */
    public function registerPostTypes()
    {
        // Get the post types from the config.
        $postTypes = config('post-types');

        // Iterate over each post type.
        collect($postTypes)->each(function ($args, $key) {
            // Register the extended post type.
            $singular = $args['names']['singular'] ?? null;
            $plural = $args['names']['plural'] ?? null;
            $slug = $args['names']['slug'] ?? null;
            PostType::make($key, $singular, $plural)->setSlug($slug)->setRawArgs($args);
        });
    }
}
