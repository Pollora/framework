<?php

declare(strict_types=1);

namespace Pollora\Hook;

/**
 * Class Hook
 *
 * Central singleton class for managing WordPress hooks (actions and filters).
 * Provides convenient access to Action and Filter instances.
 */
final class Hook
{
    /**
     * @var Action The Action instance for managing WordPress actions.
     */
    public readonly Action $action;

    /**
     * @var Filter The Filter instance for managing WordPress filters.
     */
    public readonly Filter $filter;

    /**
     * Hook constructor.
     *
     * @param  Action  $action  The Action instance.
     * @param  Filter  $filter  The Filter instance.
     */
    public function __construct(Action $action, Filter $filter)
    {
        $this->action = $action;
        $this->filter = $filter;
    }

    /**
     * Shorthand method to add an action.
     *
     * Uses reflection to automatically detect the number of arguments
     * the callback can accept if $acceptedArgs is null.
     *
     * @param  string  $hook  The name of the WordPress action.
     * @param  callable|string|array  $callback  The callback function to be executed.
     * @param  int  $priority  Optional. The priority order in which the function will be executed.
     *                         Default 10.
     * @param  int|null  $acceptedArgs  Optional. The number of arguments the function accepts.
     *                                  If null, it will be auto-detected. Default null.
     */
    public function addAction(string $hook, callable|string|array $callback, int $priority = 10, ?int $acceptedArgs = null): void
    {
        $this->action->add($hook, $callback, $priority, $acceptedArgs);
    }

    /**
     * Shorthand method to add a filter.
     *
     * Uses reflection to automatically detect the number of arguments
     * the callback can accept if $acceptedArgs is null.
     *
     * @param  string  $hook  The name of the WordPress filter.
     * @param  callable|string|array  $callback  The callback function to be executed.
     * @param  int  $priority  Optional. The priority order in which the function will be executed.
     *                         Default 10.
     * @param  int|null  $acceptedArgs  Optional. The number of arguments the function accepts.
     *                                  If null, it will be auto-detected. Default null.
     */
    public function addFilter(string $hook, callable|string|array $callback, int $priority = 10, ?int $acceptedArgs = null): void
    {
        $this->filter->add($hook, $callback, $priority, $acceptedArgs);
    }

    /**
     * Shorthand method to remove an action.
     *
     * @param  string  $hook  The name of the WordPress action.
     * @param  callable  $callback  The callback function to remove.
     * @param  int  $priority  Optional. The priority of the function to remove.
     *                         Default 10.
     */
    public function removeAction(string $hook, callable $callback, int $priority = 10): void
    {
        $this->action->remove($hook, $callback, $priority);
    }

    /**
     * Shorthand method to remove a filter.
     *
     * @param  string  $hook  The name of the WordPress filter.
     * @param  callable  $callback  The callback function to remove.
     * @param  int  $priority  Optional. The priority of the function to remove.
     *                         Default 10.
     */
    public function removeFilter(string $hook, callable $callback, int $priority = 10): void
    {
        $this->filter->remove($hook, $callback, $priority);
    }

    /**
     * Shorthand method to check if an action exists.
     *
     * @param  string  $hook  The name of the WordPress action.
     * @param  callable|string|array|null  $callback  Optional. The callback to check for.
     * @param  int|null  $priority  Optional. The priority of the function to check for.
     * @return bool True if the action or specified callback exists, false otherwise.
     */
    public function hasAction(string $hook, callable|string|array|null $callback = null, ?int $priority = null): bool
    {
        return $this->action->exists($hook, $callback, $priority);
    }

    /**
     * Shorthand method to check if a filter exists.
     *
     * @param  string  $hook  The name of the WordPress filter.
     * @param  callable|string|array|null  $callback  Optional. The callback to check for.
     * @param  int|null  $priority  Optional. The priority of the function to check for.
     * @return bool True if the filter or specified callback exists, false otherwise.
     */
    public function hasFilter(string $hook, callable|string|array|null $callback = null, ?int $priority = null): bool
    {
        return $this->filter->exists($hook, $callback, $priority);
    }

    /**
     * Shorthand method to apply a filter.
     *
     * @param  string  $hook  The filter hook name.
     * @param  mixed  $value  The value to filter.
     * @param  mixed  ...$args  Optional additional arguments to pass to the callbacks.
     * @return mixed The filtered value after all callbacks are applied.
     */
    public function applyFilter(string $hook, $value, ...$args): mixed
    {
        return $this->filter->apply($hook, $value, ...$args);
    }

    /**
     * Shorthand method to do an action.
     *
     * @param  string  $hook  The action hook name.
     * @param  mixed  ...$args  Optional arguments to pass to the callbacks.
     */
    public function doAction(string $hook, ...$args): void
    {
        $this->action->do($hook, ...$args);
    }
}
