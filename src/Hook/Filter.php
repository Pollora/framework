<?php

declare(strict_types=1);

namespace Pollora\Hook;

use Pollora\Hook\Contracts\FilterInterface;

/**
 * WordPress Filter Hook implementation.
 * 
 * Provides functionality for working with WordPress filter hooks,
 * allowing modification of data at specific points in the application.
 */
class Filter extends AbstractHook implements FilterInterface
{
    /**
     * Apply a WordPress filter hook.
     *
     * @param string $hook  The filter hook name to apply
     * @param mixed  $value The value to filter
     * @param mixed  ...$args Additional arguments to pass to the filter
     * @return mixed The filtered value
     */
    public function apply(string $hook, mixed $value, ...$args): mixed
    {
        return apply_filters($hook, $value, ...$args);
    }
}
