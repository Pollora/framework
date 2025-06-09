<?php

declare(strict_types=1);

namespace Pollora\View\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Pollora\Filesystem\Filesystem;
use Pollora\Hook\Infrastructure\Services\Filter;
use Pollora\View\Application\Services\TemplateHierarchyService;
use Pollora\View\Application\UseCases\RegisterTemplateHierarchyFiltersUseCase;
use Pollora\View\Application\UseCases\ResolveBladeTemplateUseCase;
use Pollora\View\Domain\Contracts\TemplateFinderInterface;
use Pollora\View\Domain\Contracts\TemplateHierarchyFilterInterface;
use Pollora\View\Infrastructure\Services\FileSystemTemplateFinder;
use Pollora\View\Infrastructure\Services\WordPressTemplateHierarchyFilter;

/**
 * Service provider for the Pollora template hierarchy system.
 *
 * This provider registers the hexagonal architecture-based template hierarchy
 * system that integrates Blade templates with WordPress template hierarchy.
 */
class TemplateHierarchyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->registerDomainContracts();
        $this->registerUseCases();
        $this->registerApplicationServices();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->initializeTemplateHierarchy();
    }

    /**
     * Register domain contracts with their infrastructure implementations.
     */
    private function registerDomainContracts(): void
    {
        // Register Filesystem
        $this->app->singleton(Filesystem::class);

        // Template Finder Interface
        $this->app->bind(TemplateFinderInterface::class, function ($app) {
            return new FileSystemTemplateFinder(
                $app->get('view')->getFinder(),
                $app->make(Filesystem::class)
            );
        });

        // Template Hierarchy Filter Interface
        $this->app->bind(TemplateHierarchyFilterInterface::class, function ($app) {
            return new WordPressTemplateHierarchyFilter(
                $app->make(TemplateFinderInterface::class),
                $app->make(ResolveBladeTemplateUseCase::class),
                $app->make(Filesystem::class)
            );
        });
    }

    /**
     * Register application use cases.
     */
    private function registerUseCases(): void
    {
        // Resolve Blade Template Use Case
        $this->app->bind(ResolveBladeTemplateUseCase::class, function ($app) {
            return new ResolveBladeTemplateUseCase(
                $app->make(TemplateFinderInterface::class),
                $app->get('view')
            );
        });

        // Register Template Hierarchy Filters Use Case
        $this->app->bind(RegisterTemplateHierarchyFiltersUseCase::class, function ($app) {
            return new RegisterTemplateHierarchyFiltersUseCase(
                $app->make(Filter::class),
                $app->make(TemplateHierarchyFilterInterface::class)
            );
        });
    }

    /**
     * Register application services.
     */
    private function registerApplicationServices(): void
    {
        // Main Template Hierarchy Service
        $this->app->singleton(TemplateHierarchyService::class, function ($app) {
            return new TemplateHierarchyService(
                $app->make(RegisterTemplateHierarchyFiltersUseCase::class)
            );
        });
    }

    /**
     * Initialize the template hierarchy system.
     */
    private function initializeTemplateHierarchy(): void
    {
        // Initialize the template hierarchy system
        $templateHierarchyService = $this->app->make(TemplateHierarchyService::class);
        $templateHierarchyService->initialize();
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array<string>
     */
    public function provides(): array
    {
        return [
            Filesystem::class,
            TemplateFinderInterface::class,
            TemplateHierarchyFilterInterface::class,
            ResolveBladeTemplateUseCase::class,
            RegisterTemplateHierarchyFiltersUseCase::class,
            TemplateHierarchyService::class,
        ];
    }
}
