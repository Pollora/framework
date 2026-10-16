<?php

declare(strict_types=1);

namespace Pollora\BlockBinding\Infrastructure\Laravel;

use Illuminate\Contracts\Container\Container;
use Pollora\BlockBinding\Domain\Contracts\MethodInvokerInterface;

/**
 * Calls a source's method through Laravel's container, which injects the
 * services its typed parameters name.
 */
final readonly class LaravelMethodInvoker implements MethodInvokerInterface
{
    public function __construct(private Container $container) {}

    public function call(object $instance, string $method, array $parameters): mixed
    {
        return $this->container->call([$instance, $method], $parameters);
    }
}
