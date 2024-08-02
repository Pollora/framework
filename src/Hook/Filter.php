<?php

declare(strict_types=1);

namespace Pollen\Hook;

use Closure;
use Pollen\Hook\AbstractHook;
use Pollen\Hook\Contracts\FilterInterface;

class Filter extends AbstractHook implements FilterInterface
{
    public function apply(string $hook, mixed $args = null): mixed
    {
        if (is_array($args)) {
            return apply_filters_ref_array($hook, $args);
        }

        return apply_filters($hook, $args);
    }

    protected function addHookEvent(string $hook, array|string|Closure $callback, int $priority, int $acceptedArgs): void
    {
        $this->hooks[$hook] = [$callback, $priority, $acceptedArgs];
        add_filter($hook, $callback, $priority, $acceptedArgs);
    }

    protected function removeHookEvent(string $hook, array|string|Closure $callback, int $priority): void
    {
        remove_filter($hook, $callback, $priority);
    }
}
