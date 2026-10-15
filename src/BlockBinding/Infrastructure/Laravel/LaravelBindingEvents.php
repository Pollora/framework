<?php

declare(strict_types=1);

namespace Pollora\BlockBinding\Infrastructure\Laravel;

use Illuminate\Contracts\Container\Container;
use Pollora\BlockBinding\Domain\Contracts\BindingEventsInterface;
use Pollora\BlockBinding\Domain\Events\BindingResolved;

/**
 * Hands BindingResolved to Laravel's event dispatcher, when the application has
 * one and a debugging tool listens.
 */
final readonly class LaravelBindingEvents implements BindingEventsInterface
{
    public function __construct(private Container $container) {}

    public function isListening(): bool
    {
        return $this->container->bound('events') && $this->container->make('events')->hasListeners(BindingResolved::class);
    }

    public function dispatch(BindingResolved $event): void
    {
        $this->container->make('events')->dispatch($event);
    }
}
