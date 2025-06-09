<?php

declare(strict_types=1);

namespace Pollora\Theme\Infrastructure\Services;

use Illuminate\Contracts\Foundation\Application;
use Pollora\Theme\Domain\Contracts\ContainerInterface;

/**
 * Laravel implementation of the Theme ContainerInterface.
 *
 * This adapter wraps the Laravel Application instance and provides
 * the domain-specific container operations.
 */
class LaravelContainerAdapter implements ContainerInterface
{
    /**
     * Create a new Laravel container adapter.
     */
    public function __construct(protected Application $app) {}

    /**
     * Register a service provider with the container.
     */
    public function registerProvider(string|object $provider): void
    {
        $this->app->register($provider);
    }

    /**
     * Register a shared binding in the container.
     */
    public function bindShared(string $abstract, mixed $concrete): void
    {
        $this->app->singleton($abstract, $concrete);
    }

    /**
     * Check if configuration is cached.
     */
    public function isConfigurationCached(): bool
    {
        // Simple check - if the application has the method, use it
        if (method_exists($this->app, 'configurationIsCached')) {
            try {
                return $this->app->configurationIsCached();
            } catch (\Throwable $e) {
                // Fall through to fallback
            }
        }

        // Fallback implementation - assume not cached
        return false;
    }

    /**
     * Get a configuration value.
     */
    public function getConfig(string $key, mixed $default = null): mixed
    {
        return $this->app['config']->get($key, $default);
    }

    /**
     * Set a configuration value.
     */
    public function setConfig(string $key, mixed $value): void
    {
        $this->app['config']->set($key, $value);
    }

    /**
     * Determine if a given type is shared.
     */
    public function has(string $id): bool
    {
        return $this->app->bound($id);
    }

    /**
     * Get a binding from the container.
     */
    public function get(string $id): mixed
    {
        return $this->app->make($id);
    }
}
