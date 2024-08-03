<?php

declare(strict_types=1);

namespace Pollen\Hook;

use Illuminate\Support\Collection;
use Pollen\Hook\Contracts\HookInterface;

abstract class AbstractHook implements HookInterface
{
    protected Collection $hooks;

    public function __construct()
    {
        $this->hooks = collect();
    }

    public function add(string|array $hooks, callable $callback, int $priority = 10, int $acceptedArgs = 2): self
    {
        foreach ((array) $hooks as $hook) {
            $this->addHookEvent($hook, $callback, $priority, $acceptedArgs);
        }

        return $this;
    }

    public function remove(string $hook, ?callable $callback = null, int $priority = 10): self
    {
        if ($callback === null) {
            $this->hooks->forget($hook);
        } else {
            $this->removeHookEvent($hook, $callback, $priority);
        }

        return $this;
    }

    public function exists(string $hook): bool
    {
        return $this->hooks->has($hook);
    }

    protected function addHookEvent(string $hook, callable $callback, int $priority, int $acceptedArgs): void
    {
        $this->hooks->put($hook, [$callback, $priority, $acceptedArgs]);
        add_filter($hook, $callback, $priority, $acceptedArgs);
    }

    protected function removeHookEvent(string $hook, callable $callback, int $priority): void
    {
        remove_filter($hook, $callback, $priority);
        $this->hooks->forget($hook);
    }
}
