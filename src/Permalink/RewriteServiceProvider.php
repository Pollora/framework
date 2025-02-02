<?php

declare(strict_types=1);

namespace Pollora\Permalink;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Pollora\Support\Facades\Action;
use Pollora\Support\Facades\Filter;
use Pollora\Support\Uri;

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
        $this->registerUrlMacro()
            ->registerPermalinkManager()
            ->registerFilters();
    }

    /**
     * Register the removeTrailingSlash macro on the URL generator.
     */
    protected function registerUrlMacro(): self
    {
        URL::macro('removeTrailingSlash', fn (?string $url) => app(Uri::class)->removeTrailingSlash($url)
        );

        return $this;
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
        Filter::add('redirect_canonical', fn ($canonicalUrl) => app(PermalinkManager::class)->handleCanonicalRedirect($canonicalUrl)
        );

        return $this;
    }

    /**
     * Bootstrap services and register WordPress hooks.
     */
    public function boot(): void
    {
        Action::add(
            'permalink_structure_changed',
            fn ($old, $new) => app(PermalinkManager::class)->updateStructure($new),
            90
        );
    }
}
