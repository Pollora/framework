<?php

declare(strict_types=1);

namespace Pollen\Hook;

use Pollen\Hook\Contracts\FilterInterface;

class Filter extends AbstractHook implements FilterInterface
{
    public function apply(string $hook, mixed $args = null): mixed
    {
        return apply_filters($hook, $args);
        return is_array($args) && isset($args[0]) ? apply_filters_ref_array($hook, $args) : apply_filters($hook, $args);
    }
}
