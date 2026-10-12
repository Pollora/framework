<?php

declare(strict_types=1);

namespace Pollora\Discovery\Infrastructure\Adapters;

use Illuminate\Contracts\Container\Container;
use Pollora\Discovery\Domain\Contracts\InstanceResolverInterface;

/**
 * Resolves instances through Laravel's container.
 *
 * `make()` rather than PSR-11 `get()`: for an unbound class, `get()` wraps
 * the original failure in an exception that names only the class, and the
 * reason a discovery could not be built would be lost.
 */
final readonly class LaravelInstanceResolver implements InstanceResolverInterface
{
    public function __construct(
        private Container $container
    ) {}

    public function make(string $className): object
    {
        return $this->container->make($className);
    }
}
