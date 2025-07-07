<?php

declare(strict_types=1);

namespace Pollora\Modules\Infrastructure\Services;

use Illuminate\Container\Container;
use Illuminate\Routing\Router;
use Pollora\Modules\Domain\Contracts\ModuleRepositoryInterface;

/**
 * Generic module bootstrap service inspired by nwidart/laravel-modules.
 *
 * This service is now simplified and only handles Laravel Module (nwidart) specific tasks.
 * Regular themes and plugins now use the self-registration system.
 */
class ModuleBootstrap
{
    /**
     * Create a new module bootstrap instance.
     *
     * @param  Container  $app  The Laravel application container
     * @param  ModuleRepositoryInterface  $repository  The module repository for accessing modules
     * @param  Router  $router  The Laravel router for registering routes
     */
    public function __construct(
        protected Container $app,
        protected ModuleRepositoryInterface $repository,
        protected Router $router
    ) {}

    /**
     * Register all enabled modules in the application.
     *
     * This method is now deprecated as modules use self-registration.
     * Only kept for backward compatibility with Laravel Module system.
     */
    public function registerModules(): void
    {
        // Legacy method - Laravel modules now handle their own registration
        // through nwidart/laravel-modules package service providers
    }

    /**
     * Register migration paths for all enabled modules.
     *
     * This method is now deprecated as modules handle their own migrations.
     */
    public function registerMigrations(): void
    {
        // Legacy method - Laravel modules handle their own migrations
        // through nwidart/laravel-modules package
    }

    /**
     * Register translation namespaces for all enabled modules.
     *
     * This method is now deprecated as modules handle their own translations.
     */
    public function registerTranslations(): void
    {
        // Legacy method - Laravel modules handle their own translations
        // through nwidart/laravel-modules package
    }

    /**
     * Register routes for all enabled modules.
     *
     * This method is now deprecated as modules handle their own routes.
     */
    public function registerRoutes(): void
    {
        // Legacy method - Laravel modules handle their own routes
        // through nwidart/laravel-modules package
    }

    /**
     * Check if auto-discovery is enabled for a specific feature.
     *
     * @param  string  $feature  The feature name (migrations, translations, routes, etc.)
     * @return bool True if auto-discovery is enabled for the feature
     */
    private function isAutoDiscoveryEnabled(string $feature): bool
    {
        return $this->app['config']->get("modules.auto-discover.{$feature}", true);
    }

    /**
     * Get the cached module path for service provider caching.
     *
     * This path is used by Laravel's ProviderRepository to cache
     * discovered service providers for better performance.
     *
     * @return string The cached module manifest path
     */
    protected function getCachedModulePath(): string
    {
        return str_replace('services.php', 'modules.php', $this->app->getCachedServicesPath());
    }
}
