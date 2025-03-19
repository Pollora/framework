<?php

declare(strict_types=1);

namespace Pollora\Support\Facades;

use Illuminate\Support\Facades\Facade;
use Pollora\Hook\Hook;

/**
 * Class Action
 *
 * Facade for the Action class, providing convenient static access.
 *
 * @method static void add(string $hook, callable|string|array $callback, int $priority = 10, ?int $acceptedArgs = null)
 * @method static void remove(string $hook, callable|string|array $callback, int $priority = 10)
 * @method static bool exists(string $hook, callable|string|array $callback = null, int $priority = null)
 * @method static void do(string $hook, ...$args)
 * @method static void doArray(string $hook, array $args = [])
 * @method static void doOnce(string $hook, ...$args)
 * @method static array getCallbacks(string $hook, ?int $priority = null)
 *
 * @see \Pollora\Hook\Action
 * @package Pollora\Hook\Facades
 */
class Action extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return \Pollora\Hook\Action::class;
    }
}
