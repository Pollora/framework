<?php

declare(strict_types=1);

namespace Pollora\Permalink;

use Illuminate\Support\ServiceProvider;
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
    }

    /**
     * Bootstrap services and register WordPress hooks.
     */
    public function boot(): void
    {
        $this->registerWordPressFilters();
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
        $filter = $this->app->make(Filter::class);
        $manager = $this->permalinkManager;
        $filter->add(
            'redirect_canonical',
            fn ($canonicalUrl): ?string => $manager->handleCanonicalRedirect($canonicalUrl)
        );
    }

    /**
     * Register necessary WordPress actions.
     */
    private function registerWordPressActions(): void
    {
        $action = $this->app->make(Action::class);
        $manager = $this->permalinkManager;
        $action->add(
            'permalink_structure_changed',
            fn (string $old, string $new) => $manager->updateStructure($new),
            90
        );
    }
}
