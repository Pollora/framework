<?php

declare(strict_types=1);

namespace Pollora\Hook\Contracts;

interface ActionInterface extends HookInterface
{
    public function do(string $hook, ...$args): self;
}
