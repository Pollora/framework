<?php

declare(strict_types=1);

namespace Pollora\Container\Infrastructure;

use Pollora\Container\Domain\ServiceLocator;

/**
 * Class ContainerServiceLocator
 *
 * Service locator implementation that relies on a dependency injection
 * container. This infrastructure class is responsible for resolving
 * services from the given container.
 */
class ContainerServiceLocator implements ServiceLocator
{
    /**
     * The dependency injection container instance.
     *
     * @var mixed
     */
    private $container;

    /**
     * Create a new service locator instance.
     *
     * @param  mixed  $container  The dependency injection container
     */
    public function __construct($container)
    {
        $this->container = $container;
    }

    /**
     * Resolve a service from the container.
     *
     * @param  string  $serviceClass  Fully qualified class name of the service
     * @return mixed|null The resolved service instance or null when not found
     */
    public function resolve(string $serviceClass)
    {
        if (! $this->container) {
            return null;
        }

        // Handle associative array style containers
        if (is_array($this->container) && isset($this->container[$serviceClass])) {
            return $this->container[$serviceClass];
        }

        // Handle PSR-11 style containers or similar
        if (is_object($this->container) && method_exists($this->container, 'get')) {
            try {
                return $this->container->get($serviceClass);
            } catch (\Throwable $e) {
                return null;
            }
        }

        return null;
    }
}
