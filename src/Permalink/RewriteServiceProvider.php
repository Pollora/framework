<?php

declare(strict_types=1);

namespace Pollora\Permalink;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Pollora\Support\Facades\Action;
use Pollora\Support\Facades\Filter;

/**
 * Service provider for URL rewrite management.
 *
 * This service provider configures the necessary components to handle
 * URL rewrites, permalinks, and canonical redirections in the WordPress
 * application.
 */
class RewriteServiceProvider extends ServiceProvider
{
    /**
     * Register URL rewrite related services.
     */
    public function register(): void
    {
        $this->registerPermalinkManager()
            ->registerFilters();
    }

    /**
     * Register the permalink manager as a singleton.
     */
    protected function registerPermalinkManager(): self
    {
        $this->app->singleton(PermalinkManager::class);

        return $this;
    }

    /**
     * Register necessary WordPress filters.
     */
    protected function registerFilters(): self
    {
        Filter::add('redirect_canonical', static fn ($canonicalUrl) => app(PermalinkManager::class)->handleCanonicalRedirect($canonicalUrl));

        return $this;
    }

    /**
     * Bootstrap services and register WordPress hooks.
     */
    public function boot(): void
    {
        Action::add(
            'permalink_structure_changed',
            static fn (string $old, string $new) => app(PermalinkManager::class)->updateStructure($new),
            90
        );
    }
}
