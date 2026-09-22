<?php

declare(strict_types=1);

namespace Pollora\View\Infrastructure\Providers;

use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Pollora\Filesystem\Filesystem;
use Pollora\Hook\Domain\Contract\Action;
use Pollora\Hook\Domain\Contract\Filter;
use Pollora\View\Application\Services\TemplateHierarchyService;
use Pollora\View\Application\UseCases\RegisterTemplateHierarchyFiltersUseCase;
use Pollora\View\Application\UseCases\ResolveBladeTemplateUseCase;
use Pollora\View\Domain\Contracts\TemplateFinderInterface;
use Pollora\View\Domain\Contracts\TemplateHierarchyFilterInterface;
use Pollora\View\Infrastructure\Services\FileSystemTemplateFinder;
use Pollora\View\Infrastructure\Services\TemplateMarker;
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
        $this->markTheAnsweringTemplate();
    }

    /**
     * Register domain contracts with their infrastructure implementations.
     */
    private function registerDomainContracts(): void
    {
        // Register Filesystem
        $this->app->singleton(Filesystem::class);

        // Template Finder Interface
        $this->app->bind(TemplateFinderInterface::class, fn (Application $app): FileSystemTemplateFinder => new FileSystemTemplateFinder(
            $app->get('view')->getFinder(),
            $app->make(Filesystem::class)
        ));

        // Template Hierarchy Filter Interface
        $this->app->bind(TemplateHierarchyFilterInterface::class, fn (Application $app): WordPressTemplateHierarchyFilter => new WordPressTemplateHierarchyFilter(
            $app->make(TemplateFinderInterface::class),
            $app->make(ResolveBladeTemplateUseCase::class),
            $app->get('view')->getFinder()
        ));
    }

    /**
     * Register application use cases.
     */
    private function registerUseCases(): void
    {
        // Resolve Blade Template Use Case
        $this->app->bind(ResolveBladeTemplateUseCase::class, fn (Application $app): ResolveBladeTemplateUseCase => new ResolveBladeTemplateUseCase(
            $app->make(TemplateFinderInterface::class),
            $app->get('view')
        ));

        // Register Template Hierarchy Filters Use Case
        $this->app->bind(RegisterTemplateHierarchyFiltersUseCase::class, fn (Application $app): RegisterTemplateHierarchyFiltersUseCase => new RegisterTemplateHierarchyFiltersUseCase(
            $app->make(Filter::class),
            $app->make(TemplateHierarchyFilterInterface::class)
        ));
    }

    /**
     * Register application services.
     */
    private function registerApplicationServices(): void
    {
        // Remembers the template between template_include and wp_head, so it
        // has to be the same instance for both.
        $this->app->singleton(TemplateMarker::class);

        // Main Template Hierarchy Service
        $this->app->singleton(TemplateHierarchyService::class, fn (Application $app): TemplateHierarchyService => new TemplateHierarchyService(
            $app->make(RegisterTemplateHierarchyFiltersUseCase::class)
        ));
    }

    /**
     * Say which template answered, in the page, while debugging.
     *
     * Debug only: the marker is an HTML comment, so it changes no markup, but
     * naming the file that rendered a page is information a production site
     * has no reason to hand out.
     */
    private function markTheAnsweringTemplate(): void
    {
        if (! defined('WP_DEBUG') || ! WP_DEBUG) {
            return;
        }

        $marker = $this->app->make(TemplateMarker::class);

        $this->app->make(Filter::class)->add('template_include', $marker->capture(...), PHP_INT_MAX);
        $this->app->make(Action::class)->add('wp_head', $marker->emit(...));
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
            TemplateMarker::class,
        ];
    }
}
