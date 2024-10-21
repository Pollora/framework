<?php

declare(strict_types=1);

namespace Pollora\Support\Facades;

use Illuminate\Support\Facades\Facade;
use Pollora\Hook\Hook;

/**
 * @method static mixed do($hook, $args = null)
 * @method static Hook add(string|array $hooks, \Closure|string|array $callback, int $priority = 10, int $accepted_args = 3)
 * @method static bool exists(string $hook)
 * @method static Hook|false remove(string $hook, \Closure|string $callback, int $priority = 10)
 */
class Action extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'wp.action';
    }
}
