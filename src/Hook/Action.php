<?php

declare(strict_types=1);

namespace Pollora\Hook;

use Pollora\Hook\Contracts\ActionInterface;

class Action extends AbstractHook implements ActionInterface
{
    public function do(string $hook, ...$args): self
    {
        do_action($hook, ...$args);

        return $this;
    }
}
