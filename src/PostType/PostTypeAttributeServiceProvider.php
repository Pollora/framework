<?php

declare(strict_types=1);

namespace Pollora\PostType;

use Illuminate\Support\ServiceProvider;
use Nwidart\Modules\Contracts\RepositoryInterface;
use Pollora\Attributes\AttributeProcessor;
use Pollora\Discoverer\Contracts\DiscoveryRegistry;
use Pollora\Discoverer\Discoverer;
use Pollora\Hook\Infrastructure\Services\Action;
use Pollora\Hook\Infrastructure\Services\Filter;
use Pollora\PostType\Commands\PostTypeMakeCommand;

/**
 * Service provider for attribute-based post type registration.
 *
 * This provider processes post types discovered by the Discoverer system
 * and registers them with WordPress.
 */
class PostTypeAttributeServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register the make:posttype command
        if ($this->app->runningInConsole()) {
            $this->commands([
                PostTypeMakeCommand::class,
            ]);
        }
    }

    /**
     * Bootstrap services.
     *
     * Processes discovered post types and registers them with WordPress.
     */
    public function boot(DiscoveryRegistry $registry): void
    {
        $this->registerPostTypes($registry);
    }

    /**
     * Register all post types from the registry.
     *
     * @param DiscoveryRegistry $registry The discovery registry
     */
    protected function registerPostTypes(DiscoveryRegistry $registry): void
    {
        $postTypeClasses = $registry->getByType('post_type');

        foreach ($postTypeClasses as $postTypeClass) {
            $this->registerPostType($postTypeClass);
        }
    }

    /**
     * Register a single post type with WordPress.
     *
     * @param  string  $postTypeClass  The fully qualified class name of the post type
     */
    protected function registerPostType(string $postTypeClass): void
    {
        $postType = $this->app->make($postTypeClass);

        // Process attributes
        $processor = new AttributeProcessor($this->app);
        $processor->process($postType);

        // Register the post type with WordPress
        if (function_exists('register_extended_post_type')) {
            register_extended_post_type(
                $postType->getSlug(),
                $postType->getArgs()
            );
        }
    }
}
