<?php

declare(strict_types=1);

namespace Pollen\Hook;

use BadMethodCallException;
use Closure;
use Illuminate\Contracts\Foundation\Application;

abstract class Hook implements IHook
{
    protected Application $container;

    protected array $hooks = [];

    public function __construct(Application $container)
    {
        $this->container = $container;
    }

    public function add($hooks, $callback, int $priority = 10, int $accepted_args = 3): self
    {
        foreach ((array) $hooks as $hook) {
            $this->addHookEvent($hook, $callback, $priority, $accepted_args);
        }

        return $this;
    }

    public function exists(string $hook): bool
    {
        return array_key_exists($hook, $this->hooks);
    }

    public function remove(string $hook, $callback = null, int $priority = 10): self|bool
    {
        if (is_null($callback) && ! $callback = $this->getCallback($hook)) {
            return false;
        }

        [$callback, $priority, $accepted_args] = $callback ?? [];
        unset($this->hooks[$hook]);
        $this->removeAction($hook, $callback, $priority);

        return $this;
    }

    public function getCallback(string $hook): ?array
    {
        return $this->hooks[$hook] ?? null;
    }

    protected function removeAction(string $hook, $callback, int $priority): void
    {
        remove_action($hook, $callback, $priority);
    }

    protected function addHookEvent(string $hook, $callback, int $priority, int $accepted_args)
    {
        if ($callback instanceof Closure || is_array($callback) || is_string($callback)) {
            $this->addEventListener($hook, $callback, $priority, $accepted_args);

            return;
        }

        if (str_contains($callback, '@') || class_exists($callback)) {
            $callback = $this->addClassEvent($hook, $callback, $priority, $accepted_args);
        }

        return $callback;
    }

    protected function addClassEvent(string $hook, string $class, int $priority, int $accepted_args): array
    {
        $callback = $this->buildClassEventCallback($class, $hook);
        $this->addEventListener($hook, $callback, $priority, $accepted_args);

        return $callback;
    }

    protected function buildClassEventCallback(string $class, string $hook): array
    {
        [$class, $method] = $this->parseClassEvent($class, $hook);
        $instance = $this->container->make($class);

        return [$instance, $method];
    }

    protected function parseClassEvent(string $class, string $hook): array
    {
        if (str_contains($class, '@')) {
            return explode('@', $class);
        }

        $method = str_replace('-', '_', $hook);

        return [$class, $method];
    }

    protected function addEventListener(string $name, $callback, int $priority, int $accepted_args)
    {
        throw new BadMethodCallException('The "addEventListener" method must be overridden.');
    }
}
