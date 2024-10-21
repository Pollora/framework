<?php

declare(strict_types=1);

namespace Pollora\Hook\Contracts;

interface FilterInterface extends HookInterface
{
    public function apply(string $hook, mixed $value, ...$args): mixed;
}
