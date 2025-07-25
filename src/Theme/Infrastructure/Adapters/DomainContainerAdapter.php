<?php

declare(strict_types=1);

namespace Pollora\Theme\Infrastructure\Adapters;

use Pollora\Theme\Domain\Contracts\ContainerInterface;

/**
 * Domain Container Adapter for Laravel Container Integration.
 *
 * This adapter implements the domain's ContainerInterface and bridges
 * the gap between the domain layer and Laravel's IoC container.
 * Following hexagonal architecture principles, this adapter allows
 * the domain to remain framework-agnostic while still leveraging
 * Laravel's dependency injection capabilities.
 *
 * The adapter translates domain container operations to their
 * Laravel equivalents, ensuring proper encapsulation and
 * dependency inversion compliance.
 *
 * @package Pollora\Theme\Infrastructure\Adapters
 * @author  Pollora Team
 * @since   1.0.0
 *
 * @implements ContainerInterface
 */
class DomainContainerAdapter implements ContainerInterface
{
    /**
     * Laravel application container instance.
     *
     * @var mixed Laravel application container
     */
    protected mixed $app;

    /**
     * Create a new domain container adapter.
     *
     * @param mixed $app Laravel application container
     */
    public function __construct(mixed $app)
    {
        $this->app = $app;
    }

    /**
     * Retrieve a service from the container.
     *
     * Resolves a service by its identifier using Laravel's
     * container resolution mechanism. Supports both class
     * names and abstract bindings.
     *
     * @param string $id Service identifier (class name or binding key)
     * @return mixed Resolved service instance
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     *         When the service cannot be resolved
     */
    public function get(string $id): mixed
    {
        return $this->app->make($id);
    }

    /**
     * Check if a service is bound in the container.
     *
     * Determines whether a service identifier has been
     * registered in the Laravel container.
     *
     * @param string $id Service identifier to check
     * @return bool True if the service is bound, false otherwise
     */
    public function has(string $id): bool
    {
        return $this->app->bound($id);
    }

    /**
     * Register a service provider with the container.
     *
     * Allows the domain to register additional service providers
     * dynamically. Accepts both provider class names and
     * instantiated provider objects.
     *
     * @param string|object $provider Service provider class name or instance
     * @return void
     *
     * @throws \InvalidArgumentException When provider is invalid
     */
    public function registerProvider(string|object $provider): void
    {
        $this->app->register($provider);
    }

    /**
     * Bind a service as a singleton in the container.
     *
     * Registers a service binding that will be resolved only once
     * and cached for subsequent requests. Supports both concrete
     * implementations and factory callbacks.
     *
     * @param string $abstract Abstract service identifier
     * @param mixed $concrete Concrete implementation or factory callback
     * @return void
     */
    public function bindShared(string $abstract, mixed $concrete): void
    {
        $this->app->singleton($abstract, $concrete);
    }

    /**
     * Check if configuration is cached.
     *
     * In the current implementation, we always return false
     * as the theme system doesn't utilize Laravel's config
     * caching mechanism directly.
     *
     * @return bool Always returns false in current implementation
     */
    public function isConfigurationCached(): bool
    {
        return false;
    }

    /**
     * Retrieve a configuration value.
     *
     * Provides access to Laravel's configuration system through
     * the domain interface. Supports dot notation for nested
     * configuration values.
     *
     * @param string $key Configuration key (supports dot notation)
     * @param mixed $default Default value if configuration key not found
     * @return mixed Configuration value or default
     *
     * @example
     * $value = $container->getConfig('theme.default_path', '/themes');
     * $nested = $container->getConfig('database.connections.mysql.host');
     */
    public function getConfig(string $key, mixed $default = null): mixed
    {
        return $this->app['config']->get($key, $default);
    }

    /**
     * Set a configuration value.
     *
     * Allows the domain to modify configuration values at runtime.
     * Changes are not persisted to configuration files and only
     * affect the current request lifecycle.
     *
     * @param string $key Configuration key (supports dot notation)
     * @param mixed $value Value to set
     * @return void
     *
     * @example
     * $container->setConfig('theme.active', 'my-theme');
     * $container->setConfig('theme.paths.additional', ['/custom/themes']);
     */
    public function setConfig(string $key, mixed $value): void
    {
        $this->app['config']->set($key, $value);
    }
}
