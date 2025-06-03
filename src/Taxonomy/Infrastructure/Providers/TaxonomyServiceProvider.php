<?php

declare(strict_types=1);

namespace Pollora\Taxonomy\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Pollora\Taxonomy\Application\Services\TaxonomyService;
use Pollora\Taxonomy\Domain\Contracts\TaxonomyFactoryInterface;
use Pollora\Taxonomy\Domain\Contracts\TaxonomyRegistryInterface;
use Pollora\Taxonomy\Domain\Contracts\TaxonomyRepositoryInterface;
use Pollora\Taxonomy\Domain\Contracts\TaxonomyServiceInterface;
use Pollora\Taxonomy\Infrastructure\Adapters\WordPressTaxonomyRegistry;
use Pollora\Taxonomy\Infrastructure\Factories\TaxonomyFactory;
use Pollora\Taxonomy\Infrastructure\Repositories\TaxonomyRepository;
use Pollora\Taxonomy\UI\Console\TaxonomyMakeCommand;

/**
 * Service provider for taxonomy functionality.
 *
 * This provider registers all the necessary services, factories, and repositories
 * following hexagonal architecture principles and dependency injection patterns.
 */
class TaxonomyServiceProvider extends ServiceProvider
{
    /**
     * Register the taxonomy services.
     */
    public function register(): void
    {
        // Bind interfaces to implementations
        $this->app->singleton(TaxonomyFactoryInterface::class, TaxonomyFactory::class);
        $this->app->singleton(TaxonomyRegistryInterface::class, WordPressTaxonomyRegistry::class);

        // Register the repository
        $this->app->singleton(TaxonomyRepositoryInterface::class, function ($app) {
            return new TaxonomyRepository(
                $app->make(TaxonomyRegistryInterface::class)
            );
        });

        // Register the TaxonomyService with interface binding
        $this->app->singleton(TaxonomyServiceInterface::class, function ($app) {
            return new TaxonomyService(
                $app->make(TaxonomyFactoryInterface::class),
                $app->make(TaxonomyRegistryInterface::class)
            );
        });

        // Also bind concrete class for backward compatibility
        $this->app->singleton(TaxonomyService::class, function ($app) {
            return $app->make(TaxonomyServiceInterface::class);
        });

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                TaxonomyMakeCommand::class,
            ]);
        }
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish configuration
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/taxonomies.php' => config_path('taxonomies.php'),
            ], 'pollora-taxonomy-config');
        }

        // Register taxonomies from configuration
        $this->registerConfiguredTaxonomies();
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

        // Resolve the service from the container using the interface
        $taxonomyService = $this->app->make(TaxonomyServiceInterface::class);

        // Register each taxonomy
        foreach ($taxonomies as $slug => $config) {
            if (! is_array($config)) {
                continue;
            }

            $objectType = $config['links'] ?? ['post'];
            $singular = $config['names']['singular'] ?? null;
            $plural = $config['names']['plural'] ?? null;
            $args = $config['args'] ?? [];

            $taxonomyService->register($slug, $objectType, $singular, $plural, $args);
        }
    }

    /**
     * Register taxonomies defined in the configuration.
     */
    private function registerConfiguredTaxonomies(): void
    {
        $this->registerTaxonomies();
    }
}
