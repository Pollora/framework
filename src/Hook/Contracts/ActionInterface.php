<?php

declare(strict_types=1);

namespace Pollen\Hook\Contracts;

interface ActionInterface extends HookInterface
{
    public function do(string $hook, mixed $args = null): self;
}
