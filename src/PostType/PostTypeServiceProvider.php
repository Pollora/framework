<?php

declare(strict_types=1);

/**
 * Class PostTypeServiceProvider
 */

namespace Pollora\PostType;

use Illuminate\Support\ServiceProvider;
use Pollora\Support\Facades\PostType;

/**
 * Service provider for registering custom post types.
 *
 * This provider handles the registration of custom post types in WordPress,
 * integrating them with Laravel's service container and allowing for
 * configuration-based post type registration.
 */
class PostTypeServiceProvider extends ServiceProvider
{
    /**
     * Register post type services.
     *
     * Binds the PostTypeFactory to the service container for creating
     * new post type instances.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->bind('wp.posttype', fn ($app): \Pollora\PostType\PostTypeFactory => new PostTypeFactory($app));
        $this->registerPostTypes();
    }

    /**
     * Register all configured custom post types.
     *
     * Reads post type configurations from the config file and registers
     * each post type with WordPress using the PostType facade.
     *
     * @return void
     *
     * @example Configuration format:
     * [
     *     'book' => [
     *         'names' => [
     *             'singular' => 'Book',
     *             'plural' => 'Books',
     *             'slug' => 'books'
     *         ],
     *         // Additional WordPress post type arguments...
     *     ]
     * ]
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
