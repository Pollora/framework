<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Str;
use Pollora\Support\Facades\Action;

/**
 * Abstract base class for WordPress event dispatchers.
 *
 * This class provides the foundation for all WordPress event dispatchers,
 * handling the registration of WordPress hooks and their conversion to Laravel events.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
abstract class AbstractEventDispatcher
{
    /**
     * The event dispatcher instance.
     */
    protected Dispatcher $events;

    /**
     * Array of WordPress actions to listen to.
     *
     * @var array<string>
     */
    protected array $actions = [];

    /**
     * Constructor.
     */
    public function __construct(Dispatcher $events)
    {
        $this->events = $events;
    }

    /**
     * Register WordPress hooks for this dispatcher.
     */
    public function registerEvents(): void
    {
        foreach ($this->actions as $action) {
            add_action($action, [$this, 'handle' . Str::studly($action)], 10, 5);
        }
    }

    /**
     * Dispatch a Laravel event.
     *
     * @param string $event The event class name
     * @param array<mixed> $payload The event payload
     */
    protected function dispatch(string $event, array $payload = []): void
    {
        $this->events->dispatch(new $event(...$payload));
    }
}
