<?php

declare(strict_types=1);

namespace Pollora\Permalink;

use Illuminate\Support\ServiceProvider;
use Pollora\Support\Facades\Action;
use Pollora\Support\Facades\Filter;
use Illuminate\Support\Facades\URL;
use Pollora\Support\URL as PolloraURL;

class RewriteServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->registerUrlMacro()
            ->registerPermalinkManager()
            ->registerFilters();
    }

    protected function registerUrlMacro(): self
    {
        URL::macro('removeTrailingSlash', fn(?string $url) => 
            app(PolloraUrl::class)->removeTrailingSlash($url)
        );

        return $this;
    }

    protected function registerPermalinkManager(): self
    {
        $this->app->singleton(PermalinkManager::class);
        return $this;
    }

    protected function registerFilters(): self
    {
        Filter::add('redirect_canonical', fn($canonicalUrl) =>
            app(PermalinkManager::class)->handleCanonicalRedirect($canonicalUrl)
        );
        return $this;
    }

    public function boot(): void
    {
        Action::add(
            'permalink_structure_changed',
            fn($old, $new) => app(PermalinkManager::class)->updateStructure($new),
            90
        );
    }
}
