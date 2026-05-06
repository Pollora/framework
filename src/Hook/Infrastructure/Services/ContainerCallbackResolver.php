<?php

declare(strict_types=1);

namespace Pollora\Hook\Infrastructure\Services;

use Illuminate\Contracts\Container\Container;
use Pollora\Hook\Domain\Contracts\CallbackResolverInterface;

/**
 * Resolves hook callback class instances via the Laravel container.
 *
 * Enables constructor dependency injection for classes registered
 * as hook callbacks through the imperative API (facades).
 */
class ContainerCallbackResolver implements CallbackResolverInterface
{
    public function __construct(
        private readonly Container $container
    ) {}

    /**
     * {@inheritDoc}
     */
    public function resolve(string $className): object
    {
        return $this->container->make($className);
    }
}
