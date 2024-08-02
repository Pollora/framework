<?php
declare(strict_types=1);

namespace Pollen\Hook;

use Closure;
use Illuminate\Contracts\Container\Container;
use Pollen\Hook\Contracts\HookInterface;

abstract class AbstractHook implements HookInterface
{
    protected Container $container;
    protected array $hooks = [];

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function add(string|array $hooks, array|string|Closure $callback, int $priority = 10, int $acceptedArgs = 2): self
    {
        foreach ((array) $hooks as $hook) {
            $this->addHookEvent($hook, $callback, $priority, $acceptedArgs);
        }

        return $this;
    }

    public function remove(string $hook, array|string|Closure|null $callback = null, int $priority = 10): self
    {
        if (is_null($callback)) {
            if (!$callback = $this->callback($hook)) {
                return $this;
            }

            [$callback, $priority, $acceptedArgs] = $callback;
            unset($this->hooks[$hook]);
        }

        $this->removeHookEvent($hook, $callback, $priority);

        return $this;
    }

    public function exists(string $hook): bool
    {
        return array_key_exists($hook, $this->hooks);
    }

    public function callback(string $hook): ?array
    {
        return $this->hooks[$hook] ?? null;
    }

    abstract protected function addHookEvent(string $hook, array|string|Closure $callback, int $priority, int $acceptedArgs): void;
    abstract protected function removeHookEvent(string $hook, array|string|Closure $callback, int $priority): void;
}
