<?php

declare(strict_types=1);

namespace Pollen\Hook;

/**
 * ActionBuilder class
 *
 * @author Julien Lambé <julien@themosis.com>
 */
class ActionBuilder extends Hook
{
    /**
     * Run all actions registered with the hook.
     *
     * @param  string  $hook The action hook name.
     * @return $this
     */
    public function run(string $hook, mixed $args = null): self
    {
        if (is_array($args)) {
            $this->doActionRefArray($hook, $args);
        } else {
            $this->doAction($hook, $args);
        }

        return $this;
    }

    /**
     * Call a single action hook.
     *
     * @param  string  $hook The hook name.
     * @param  mixed  $args Arguments passed to the hook.
     */
    protected function doAction(string $hook, mixed $args): void
    {
        do_action($hook, $args);
    }

    /**
     * Call a single action hook with arguments as an array.
     *
     * @param  string  $hook The hook name.
     * @param  array  $args Arguments passed as an array to the hook.
     */
    protected function doActionRefArray(string $hook, array $args): void
    {
        do_action_ref_array($hook, $args);
    }

    /**
     * Add an action event for the specified hook.
     *
     * @param  array|string|\Closure  $callback
     */
    protected function addEventListener(string $name, $callback, int $priority, int $accepted_args): void
    {
        $this->hooks[$name] = [$callback, $priority, $accepted_args];
        $this->addAction($name, $callback, $priority, $accepted_args);
    }

    /**
     * Calls the WordPress add_action function to listen on a hook event.
     */
    protected function addAction(string $name, array|string|\Closure $callback, int $priority, int $accepted_args): void
    {
        add_action($name, $callback, $priority, $accepted_args);
    }
}
