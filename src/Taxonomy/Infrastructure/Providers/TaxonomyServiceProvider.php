<?php

declare(strict_types=1);

namespace Pollora\Taxonomy\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Pollora\Discovery\Domain\Contracts\DiscoveryEngineInterface;
use Pollora\Taxonomy\Application\Services\TaxonomyService;
use Pollora\Taxonomy\Domain\Contracts\TaxonomyFactoryInterface;
use Pollora\Taxonomy\Domain\Contracts\TaxonomyRegistryInterface;
use Pollora\Taxonomy\Domain\Contracts\TaxonomyRepositoryInterface;
use Pollora\Taxonomy\Domain\Contracts\TaxonomyServiceInterface;
use Pollora\Taxonomy\Infrastructure\Adapters\WordPressTaxonomyRegistry;
use Pollora\Taxonomy\Infrastructure\Factories\TaxonomyFactory;
use Pollora\Taxonomy\Infrastructure\Repositories\TaxonomyRepository;
use Pollora\Taxonomy\Infrastructure\Services\TaxonomyDiscovery;
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
        $this->app->singleton(TaxonomyRepositoryInterface::class, fn ($app): TaxonomyRepository => new TaxonomyRepository(
            $app->make(TaxonomyRegistryInterface::class)
        ));

        // Register the TaxonomyService with interface binding
        $this->app->singleton(TaxonomyServiceInterface::class, fn ($app): TaxonomyService => new TaxonomyService(
            $app->make(TaxonomyFactoryInterface::class),
            $app->make(TaxonomyRegistryInterface::class)
        ));

        // Also bind concrete class for backward compatibility
        $this->app->singleton(TaxonomyService::class, fn ($app) => $app->make(TaxonomyServiceInterface::class));

        // Register Taxonomy Discovery
        $this->app->singleton(TaxonomyDiscovery::class, fn ($app): TaxonomyDiscovery => new TaxonomyDiscovery(
            $app->make(TaxonomyServiceInterface::class)
        ));

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
        $this->registerTaxonomyDiscovery();
    }

    /**
     * Register Taxonomy discovery with the discovery engine.
     */
    private function registerTaxonomyDiscovery(): void
    {
        if ($this->app->bound(DiscoveryEngineInterface::class)) {
            /** @var DiscoveryEngineInterface $engine */
            $engine = $this->app->make(DiscoveryEngineInterface::class);
            $taxonomyDiscovery = $this->app->make(TaxonomyDiscovery::class);

            $engine->addDiscovery('taxonomies', $taxonomyDiscovery);
        }
    }
}
