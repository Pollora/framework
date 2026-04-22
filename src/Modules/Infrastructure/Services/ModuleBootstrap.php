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
