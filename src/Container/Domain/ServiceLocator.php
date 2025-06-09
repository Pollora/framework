<?php

declare(strict_types=1);

namespace Pollora\Container\Domain;

/**
 * Interface ServiceLocator
 *
 * Defines a contract for resolving services from a container.
 * This interface is part of the domain layer and is independent of any
 * specific implementation.
 */
interface ServiceLocator
{
    /**
     * Resolve a service by its class name.
     *
     * @param  string  $serviceClass  The class of the service to resolve
     * @return mixed|null The resolved service or null if not found
     */
    public function resolve(string $serviceClass);
}
