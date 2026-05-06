<?php

declare(strict_types=1);

namespace Pollora\Hook\Infrastructure\Services;

use Pollora\Hook\Domain\Contracts\Filter as FilterContract;
use Pollora\Hook\Domain\Services\AbstractHook;

/**
 * Laravel/WordPress adapter for Filter hooks.
 *
 * Implements the Filter contract and delegates to WordPress functions.
 * Uses hook-point methods (addHookEvent/removeHookEvent) to avoid
 * double callback resolution.
 */
class Filter extends AbstractHook implements FilterContract
{
    /**
     * Register a hook event with WordPress.
     */
    protected function addHookEvent(string $hook, callable|string|array $callback, int $priority, int $acceptedArgs): void
    {
        parent::addHookEvent($hook, $callback, $priority, $acceptedArgs);
        add_filter($hook, $callback, $priority, $acceptedArgs);
    }

    /**
     * Unregister a hook event from WordPress.
     */
    protected function removeHookEvent(string $hook, callable|string|array $callback, int $priority): void
    {
        remove_filter($hook, $callback, $priority);
    }

    /**
     * Apply a WordPress filter hook.
     *
     * @param  string  $hook  The filter hook name to apply
     * @param  mixed  $value  The value to filter
     * @param  mixed  ...$args  Additional arguments to pass to the filter
     * @return mixed The filtered value
     */
    public function apply(string $hook, mixed $value, ...$args): mixed
    {
        return apply_filters($hook, $value, ...$args);
    }
}
