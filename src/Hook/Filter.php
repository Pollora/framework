<?php

declare(strict_types=1);

namespace Pollen\Hook;

use Pollen\Hook\Contracts\FilterInterface;

class Filter extends AbstractHook implements FilterInterface
{
    public function apply(string $hook, mixed $value, ...$args): mixed
    {
        return apply_filters($hook, $value, ...$args);
    }
}
