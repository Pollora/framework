<?php

declare(strict_types=1);

namespace Pollora\Hook\Contracts;

interface HookInterface
{
    public function add(string|array $hooks, callable $callback, int $priority = 10, int $acceptedArgs = 2): self;

    public function remove(string $hook, ?callable $callback = null, int $priority = 10): self;

    public function exists(string $hook): bool;
}
