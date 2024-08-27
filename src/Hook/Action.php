<?php

declare(strict_types=1);

namespace Pollen\Hook;

use Pollen\Hook\Contracts\ActionInterface;

class Action extends AbstractHook implements ActionInterface
{
    public function do(string $hook, ...$args): self
    {
        do_action($hook, ...$args);

        return $this;
    }
}
