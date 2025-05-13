<?php

declare(strict_types=1);

namespace Pollora\Permalink;

use Illuminate\Support\ServiceProvider;
use Pollora\Container\Infrastructure\ContainerServiceLocator;
use Pollora\Hook\Infrastructure\Services\Action;
use Pollora\Hook\Infrastructure\Services\Filter;

/**
 * Service provider for URL rewrite management.
 *
 * This service provider configures the necessary components to handle
 * URL rewrites, permalinks, and canonical redirections in the WordPress
 * application.
 */
class RewriteServiceProvider extends ServiceProvider
{
    private PermalinkManager $permalinkManager;

    /**
     * Register URL rewrite related services.
     */
    public function register(): void
    {
        $this->registerPermalinkManager();
        $this->registerWordPressFilters();
    }

    /**
     * Bootstrap services and register WordPress hooks.
     */
    public function boot(): void
    {
        $this->registerWordPressActions();
    }

    /**
     * Register the permalink manager as a singleton.
     */
    private function registerPermalinkManager(): void
    {
        $this->permalinkManager = new PermalinkManager;
    }

    /**
     * Register necessary WordPress filters.
     */
    private function registerWordPressFilters(): void
    {
        $filter = $this->getServiceLocator()->resolve(Filter::class);
        $manager = $this->permalinkManager;
        $filter->add(
            'redirect_canonical',
            fn ($canonicalUrl) => $manager->handleCanonicalRedirect($canonicalUrl)
        );
    }

    /**
     * Register necessary WordPress actions.
     */
    private function registerWordPressActions(): void
    {
        $action = $this->getServiceLocator()->resolve(Action::class);
        $manager = $this->permalinkManager;
        $action->add(
            'permalink_structure_changed',
            fn (string $old, string $new) => $manager->updateStructure($new),
            90
        );
    }

    /**
     * Get the service locator instance.
     */
    private function getServiceLocator(): ContainerServiceLocator
    {
        return new ContainerServiceLocator($this->app);
    }
}
