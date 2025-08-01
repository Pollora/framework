<?php

declare(strict_types=1);

namespace Pollora\Hook\Domain\Contracts;

/**
 * Interface for WordPress Filter hooks.
 *
 * Defines the contract for applying WordPress filters.
 */
interface Filter extends HookInterface
{
    /**
     * Apply a WordPress filter hook.
     *
     * @param  string  $hook  The filter hook name
     * @param  mixed  $value  The value to filter
     * @param  mixed  ...$args  Additional arguments
     * @return mixed The filtered value
     */
    public function apply(string $hook, mixed $value, mixed ...$args): mixed;
}
