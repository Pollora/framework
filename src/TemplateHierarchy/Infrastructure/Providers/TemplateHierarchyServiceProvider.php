<?php

declare(strict_types=1);

namespace Pollora\TemplateHierarchy\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Pollora\Hook\Infrastructure\Services\Action;
use Pollora\Hook\Infrastructure\Services\Filter;
use Pollora\TemplateHierarchy\TemplateHierarchy;

/**
 * Service provider for the Template Hierarchy System.
 */
class TemplateHierarchyServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->app->singleton(TemplateHierarchy::class, function ($app) {
            return new TemplateHierarchy(
                $app,
                $app['config'],
                $app->make(Action::class),
                $app->make(Filter::class)
            );
        });
    }

    /**
     * Bootstrap the service provider.
     */
    public function boot(): void
    {
        // Register default configuration
        $this->mergeConfigFrom(
            __DIR__.'/../config/template-hierarchy.php',
            'wordpress'
        );

        // Publish configuration
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/template-hierarchy.php' => config_path('wordpress/template-hierarchy.php'),
            ], 'pollora-config');
        }
    }
}
