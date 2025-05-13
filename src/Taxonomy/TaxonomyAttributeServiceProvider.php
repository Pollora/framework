<?php

declare(strict_types=1);

namespace Pollora\Taxonomy;

use Illuminate\Support\ServiceProvider;
use Pollora\Attributes\AttributeProcessor;
use Pollora\Discoverer\Contracts\DiscoveryRegistry;
use Pollora\Taxonomy\Commands\TaxonomyMakeCommand;

/**
 * Service provider for attribute-based taxonomy registration.
 *
 * This provider processes taxonomies discovered by the Discoverer system
 * and registers them with WordPress.
 */
class TaxonomyAttributeServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register the make:taxonomy command
        if ($this->app->runningInConsole()) {
            $this->commands([
                TaxonomyMakeCommand::class,
            ]);
        }
    }

    /**
     * Bootstrap services.
     *
     * Processes discovered taxonomies and registers them with WordPress.
     */
    public function boot(DiscoveryRegistry $registry): void
    {
        $this->registerTaxonomies($registry);
    }

    /**
     * Register all taxonomies from the registry.
     *
     * @param  DiscoveryRegistry  $registry  The discovery registry
     */
    protected function registerTaxonomies(DiscoveryRegistry $registry): void
    {
        $taxonomyClasses = $registry->getByType('taxonomy');

        foreach ($taxonomyClasses as $taxonomyClass) {
            $this->registerTaxonomy($taxonomyClass);
        }
    }

    /**
     * Register a single taxonomy with WordPress.
     *
     * Creates an instance of the taxonomy class, processes its attributes,
     * and registers it with WordPress using register_taxonomy().
     *
     * @param  string  $taxonomyClass  The fully qualified class name of the taxonomy
     */
    protected function registerTaxonomy(string $taxonomyClass): void
    {
        $taxonomy = $this->app->make($taxonomyClass);

        // Process attributes
        $processor = new AttributeProcessor($this->app);
        $processor->process($taxonomy);

        // Register the taxonomy with WordPress
        Taxonomy::make($taxonomy->getSlug(), $taxonomy->getObjectType())
            ->setRawArgs($taxonomy->getArgs());
    }
}
