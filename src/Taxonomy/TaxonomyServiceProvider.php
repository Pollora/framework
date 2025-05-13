<?php

declare(strict_types=1);

/**
 * Class TaxonomyServiceProvider
 */

namespace Pollora\Taxonomy;

use Illuminate\Support\ServiceProvider;
use Pollora\Entity\Taxonomy;
use Pollora\Taxonomy\Application\Services\TaxonomyService;
use Pollora\Taxonomy\Domain\Contracts\TaxonomyFactoryInterface;

/**
 * Class TaxonomyServiceProvider
 *
 * A service provider for registering custom taxonomies following hexagonal architecture principles.
 */
class TaxonomyServiceProvider extends ServiceProvider
{
    /**
     * Register taxonomy services.
     *
     * Binds the TaxonomyFactory and TaxonomyService to the service container
     * following the hexagonal architecture principles.
     */
    public function register(): void
    {
        // Bind the interface to the concrete implementation
        $this->app->singleton(TaxonomyFactoryInterface::class, TaxonomyFactory::class);
        
        // Register the TaxonomyService
        $this->app->singleton(TaxonomyService::class, function ($app) {
            return new TaxonomyService(
                $app->make(TaxonomyFactoryInterface::class)
            );
        });
        
        // Legacy binding for backward compatibility
        $this->app->alias(TaxonomyFactoryInterface::class, 'wp.taxonomy');
        
        $this->registerTaxonomies();
    }

    /**
     * Register all the site's custom taxonomies
     * 
     * Reads taxonomy configurations from the config file and registers
     * each taxonomy using the TaxonomyService, following hexagonal architecture
     * principles by using dependency injection instead of facades.
     */
    public function registerTaxonomies(): void
    {
        // Get the taxonomies from the config
        $taxonomies = $this->app['config']->get('taxonomies', []);
        
        // Resolve the service from the container
        $taxonomyService = $this->app->make(TaxonomyService::class);

        // Iterate over each taxonomy
        collect($taxonomies)->each(function (array $args, string $key) use ($taxonomyService): void {
            // Get the taxonomy configuration
            $links = $args['links'] ?? [];
            $singular = $args['names']['singular'] ?? null;
            $plural = $args['names']['plural'] ?? null;
            $slug = $args['names']['slug'] ?? null;
            
            // Create the taxonomy instance using the service and configure it
            $taxonomy = $taxonomyService->register($key, $links, $singular, $plural);
            
            // Set additional configuration if needed
            if ($slug !== null) {
                $taxonomy->setSlug($slug);
            }
            
            // Set any raw arguments
            if (isset($args) && is_array($args)) {
                $taxonomy->setRawArgs($args);
            }
        });
    }
}
