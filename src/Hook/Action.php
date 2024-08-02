<?php

declare(strict_types=1);

namespace Pollen\Hook;

use Closure;
use Pollen\Hook\AbstractHook;
use Pollen\Hook\Contracts\ActionInterface;

class Action extends AbstractHook implements ActionInterface
{
    public function do(string $hook, mixed $args = null): self
    {
        if (is_array($args)) {
            do_action_ref_array($hook, $args);
        } else {
            do_action($hook, $args);
        }

        return $this;
    }

    protected function addHookEvent(string $hook, array|string|Closure $callback, int $priority, int $acceptedArgs): void
    {
        $this->hooks[$hook] = [$callback, $priority, $acceptedArgs];
        add_action($hook, $callback, $priority, $acceptedArgs);
    }

    protected function removeHookEvent(string $hook, array|string|Closure $callback, int $priority): void
    {
        remove_action($hook, $callback, $priority);
    }
}
