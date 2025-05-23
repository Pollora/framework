<?php

declare(strict_types=1);

namespace Pollora\Taxonomy\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Pollora\Attributes\AttributeProcessor;
use Pollora\Discoverer\Domain\Contracts\DiscoveryRegistryInterface;
use Pollora\Taxonomy\Application\Services\TaxonomyService;
use Pollora\Taxonomy\Domain\Models\AbstractTaxonomy;

/**
 * Service provider for attribute-based taxonomy registration.
 *
 * This provider processes taxonomies discovered by the Discoverer system
 * and registers them with WordPress following hexagonal architecture principles.
 */
class TaxonomyAttributeServiceProvider extends ServiceProvider
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
     * Processes discovered taxonomies and registers them with WordPress.
     */
    public function boot(DiscoveryRegistryInterface $registry): void
    {
        $this->registerTaxonomies($registry);
    }

    /**
     * Register all taxonomies from the registry.
     *
     * @param  DiscoveryRegistryInterface  $registry  The discovery registry
     */
    protected function registerTaxonomies(DiscoveryRegistryInterface $registry): void
    {
        $taxonomyClasses = $registry->getByType('taxonomy');

        if (empty($taxonomyClasses)) {
            return;
        }

        $processor = new AttributeProcessor($this->app);

        foreach ($taxonomyClasses as $taxonomyClass) {
            $this->registerTaxonomy($taxonomyClass->getClassName(), $processor);
        }
    }

    /**
     * Register a single taxonomy with WordPress.
     *
     * @param  string  $taxonomyClass  The fully qualified class name of the taxonomy
     * @param  AttributeProcessor  $processor  The attribute processor
     */
    protected function registerTaxonomy(
        string $taxonomyClass,
        AttributeProcessor $processor
    ): void {
        $taxonomyService = $this->app->make(TaxonomyService::class);
        $taxonomyInstance = $this->app->make($taxonomyClass);

        if (! $taxonomyInstance instanceof AbstractTaxonomy) {
            return;
        }

        // Process attributes
        $processor->process($taxonomyInstance);

        // Get taxonomy properties
        $slug = $taxonomyInstance->getSlug();
        $objectType = $taxonomyInstance->getObjectType();
        $singular = $taxonomyInstance->getName();
        $plural = $taxonomyInstance->getPluralName();
        $args = $taxonomyInstance->getArgs();

        $taxonomyService->register($slug, $objectType, $singular, $plural, $args);
    }
}
