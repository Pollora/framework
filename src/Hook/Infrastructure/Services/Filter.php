<?php

declare(strict_types=1);

namespace Pollora\Hook\Infrastructure\Services;

use Pollora\Hook\Domain\Contracts\Filter as FilterContract;
use Pollora\Hook\Domain\Services\AbstractHook;

/**
 * Laravel/WordPress adapter for Filter hooks.
 *
 * Implements the Filter contract and delegates to WordPress functions.
 */
class Filter extends AbstractHook implements FilterContract
{
    /**
     * Add one or multiple filter hooks and register with WordPress.
     */
    public function add(string|array $hooks, callable|string|array $callback, int $priority = 10, ?int $acceptedArgs = null): self
    {
        parent::add($hooks, $callback, $priority, $acceptedArgs);
        foreach ((array) $hooks as $hook) {
            $resolved = $this->resolveCallback($hook, $callback, $acceptedArgs);
            add_filter($hook, $resolved['callable'], $priority, $resolved['args']);
        }

        return $this;
    }

    /**
     * Remove a filter hook and unregister from WordPress.
     */
    public function remove(string $hook, callable|string|array|null $callback = null, int $priority = 10): self|false
    {
        $result = parent::remove($hook, $callback, $priority);
        if ($callback !== null) {
            $resolved = $this->resolveCallback($hook, $callback, null);
            remove_filter($hook, $resolved['callable'], $priority);
        }

        return $result;
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
