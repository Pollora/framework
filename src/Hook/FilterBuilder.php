<?php

declare(strict_types=1);

namespace Pollen\Hook;

/**
 * FilterBuilder class
 *
 * @author Julien Lambé <julien@themosis.com>
 */
class FilterBuilder extends Hook
{
    /**
     * Run all filters registered with the hook.
     *
     * @param  string  $hook The filter hook name.
     */
    public function run(string $hook, mixed $args = null): mixed
    {
        if (is_array($args)) {
            return $this->applyFiltersRefArray($hook, $args);
        }

        return $this->applyFilters($hook, $args);
    }

    /**
     * Shortcut for run method.
     *
     * @param  string  $hook The filter hook name.
     */
    public function apply(string $hook, mixed $args = null): mixed
    {
        return $this->run($hook, $args);
    }

    /**
     * Call a filter hook with data as an array.
     *
     * @param  string  $hook The hook name.
     * @param  array  $args Filter data passed with the hook as an array.
     */
    protected function applyFiltersRefArray(string $hook, array $args): mixed
    {
        return apply_filters_ref_array($hook, $args);
    }

    /**
     * Call a filter hook.
     *
     * @param  string  $hook The hook name.
     * @param  mixed  $args Filter data passed with the hook.
     */
    protected function applyFilters(string $hook, mixed $args): mixed
    {
        return apply_filters($hook, $args);
    }

    /**
     * Add a filter event for the specified hook.
     */
    protected function addEventListener(string $name, array|string|\Closure $callback, int $priority, int $accepted_args): void
    {
        $this->hooks[$name] = [$callback, $priority, $accepted_args];
        $this->addFilter($name, $callback, $priority, $accepted_args);
    }

    /**
     * Calls the WordPress add_filter function in order to listen to a filter hook.
     */
    protected function addFilter(string $name, array|string|\Closure $callback, int $priority, int $accepted_args): void
    {
        add_filter($name, $callback, $priority, $accepted_args);
    }
}
