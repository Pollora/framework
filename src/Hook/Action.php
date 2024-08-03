<?php

declare(strict_types=1);

namespace Pollen\Hook;

use Pollen\Hook\Contracts\ActionInterface;

class Action extends AbstractHook implements ActionInterface
{
    public function do(string $hook, mixed $args = null): self
    {
        if (is_array($args)) {
            do_action_ref_array($hook, $args);
        } else {
            do_action($hook, $args);
        }

        return $this;
    }
}
