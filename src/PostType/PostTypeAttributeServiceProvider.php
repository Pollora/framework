<?php

declare(strict_types=1);

namespace Pollora\PostType;

use Illuminate\Support\ServiceProvider;
use Pollora\Attributes\AttributeProcessor;
use Pollora\PostType\Commands\PostTypeMakeCommand;
use Pollora\PostType\Contracts\PostType;
use Spatie\StructureDiscoverer\Discover;

/**
 * Service provider for attribute-based post type registration.
 *
 * This provider discovers and registers all classes implementing the PostType interface
 * and processes their PHP attributes to configure WordPress custom post types.
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
     * Discovers and registers all post types defined using PHP attributes.
     */
    public function boot(): void
    {
        $this->registerPostTypes();
    }

    /**
     * Register all post types defined using PHP attributes.
     *
     * Discovers all classes implementing the PostType interface, processes their
     * attributes, and registers them with WordPress.
     */
    protected function registerPostTypes(): void
    {
        // Check if the directory exists before attempting to discover classes
        $directory = app_path('Cms/PostTypes');
        if (! is_dir($directory)) {
            return; // Return early as there are no classes to discover yet
        }

        // Discover all classes implementing the PostType interface
        $postTypeClasses = Discover::in($directory)
            ->extending(AbstractPostType::class)
            ->classes()
            ->get();

        // Register each post type with WordPress
        if (! empty($postTypeClasses)) {
            foreach ($postTypeClasses as $postTypeClass) {
                $this->registerPostType($postTypeClass);
            }
        }
    }

    /**
     * Register a single post type with WordPress.
     *
     * Creates an instance of the post type class, processes its attributes,
     * and registers it with WordPress using register_post_type().
     *
     * @param  string  $postTypeClass  The fully qualified class name of the post type
     */
    protected function registerPostType(string $postTypeClass): void
    {
        $postType = $this->app->make($postTypeClass);

        // Process attributes
        AttributeProcessor::process($postType);

        // Register the post type with WordPress
        if (function_exists('register_extended_post_type')) {
            register_extended_post_type(
                $postType->getSlug(),
                $postType->getArgs()
            );
        }
    }
}
