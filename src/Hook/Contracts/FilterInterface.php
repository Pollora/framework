<?php

declare(strict_types=1);

namespace Pollen\Hook\Contracts;

interface FilterInterface extends HookInterface
{
    public function apply(string $hook, mixed $args = null): mixed;
}
