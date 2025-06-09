<?php

declare(strict_types=1);

namespace Pollora\PostType\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Pollora\Attributes\AttributeProcessor;
use Pollora\Discoverer\Framework\API\PolloraDiscover;
use Pollora\PostType\Application\Services\PostTypeService;
use Pollora\PostType\Domain\Models\AbstractPostType;

/**
 * Service provider for attribute-based post type registration.
 *
 * This provider processes post types discovered by the Discoverer system
 * and registers them with WordPress following hexagonal architecture principles.
 */
class PostTypeAttributeServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // No registration needed as the main service provider handles it
    }

    /**
     * Bootstrap services.
     *
     * Processes discovered post types and registers them with WordPress.
     */
    public function boot(): void
    {
        $this->registerPostTypes();
    }

    /**
     * Register all post types using the new discovery system.
     */
    protected function registerPostTypes(): void
    {
        try {
            $postTypeClasses = PolloraDiscover::scout('post_types');

            if ($postTypeClasses->isEmpty()) {
                return;
            }

            $processor = new AttributeProcessor($this->app);

            foreach ($postTypeClasses as $postTypeClass) {
                $this->registerPostType($postTypeClass, $processor);
            }
        } catch (\Throwable $e) {
            // Log error but don't break the application
            if (function_exists('error_log')) {
                error_log('Failed to load post types: '.$e->getMessage());
            }
        }
    }

    /**
     * Register a single post type with WordPress.
     *
     * @param  string  $postTypeClass  The fully qualified class name of the post type
     * @param  AttributeProcessor  $processor  The attribute processor
     */
    protected function registerPostType(
        string $postTypeClass,
        AttributeProcessor $processor
    ): void {
        $postTypeService = $this->app->make(PostTypeService::class);
        $postTypeInstance = $this->app->make($postTypeClass);

        if (! $postTypeInstance instanceof AbstractPostType) {
            return;
        }

        // Process attributes
        $processor->process($postTypeInstance);

        // Get post type properties
        $slug = $postTypeInstance->getSlug();
        $singular = $postTypeInstance->getName();
        $plural = $postTypeInstance->getPluralName();
        $args = $postTypeInstance->getArgs();

        $postTypeService->register($slug, $singular, $plural, $args);
    }
}
