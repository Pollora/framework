<?php

declare(strict_types=1);

namespace Pollora\PostType\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Pollora\Attributes\AttributeProcessor;
use Pollora\Discoverer\Domain\Contracts\DiscoveryRegistryInterface;
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
    public function boot(DiscoveryRegistryInterface $registry): void
    {
        $this->registerPostTypes($registry);
    }

    /**
     * Register all post types from the registry.
     *
     * @param  DiscoveryRegistryInterface  $registry  The discovery registry
     */
    protected function registerPostTypes(DiscoveryRegistryInterface $registry): void
    {
        $postTypeClasses = $registry->getByType('post_type');

        if (empty($postTypeClasses)) {
            return;
        }
        $processor = new AttributeProcessor($this->app);

        foreach ($postTypeClasses as $postTypeClass) {
            $this->registerPostType($postTypeClass->getClassName(), $processor);
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
