<?php

declare(strict_types=1);

/**
 * Class PostTypeServiceProvider
 */

namespace Pollora\PostType;

use Illuminate\Support\ServiceProvider;
use Pollora\Support\Facades\PostType;

/**
 * Class PostTypeServiceProvider
 *
 * A service provider for registering custom post types.
 */
class PostTypeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind('wp.posttype', fn($app): \Pollora\PostType\PostTypeFactory => new PostTypeFactory($app));
        $this->registerPostTypes();
    }

    /**
     * Register all the site's custom post types
     */
    public function registerPostTypes(): void
    {
        // Get the post types from the config.
        $postTypes = config('post-types');

        // Iterate over each post type.
        collect($postTypes)->each(function (array $args, $key): void {
            // Register the extended post type.
            $singular = $args['names']['singular'] ?? null;
            $plural = $args['names']['plural'] ?? null;
            $slug = $args['names']['slug'] ?? null;
            PostType::make($key, $singular, $plural)->setSlug($slug)->setRawArgs($args);
        });
    }
}
