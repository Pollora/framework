<?php

declare(strict_types=1);

namespace Pollen\Hook\Contracts;

use Closure;

interface HookInterface
{
    public function add(string|array $hooks, array|string|Closure $callback, int $priority = 10, int $acceptedArgs = 2): self;
    public function remove(string $hook, array|string|Closure|null $callback = null, int $priority = 10): self;
    public function exists(string $hook): bool;
    public function callback(string $hook): ?array;
}
