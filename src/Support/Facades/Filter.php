<?php

declare(strict_types=1);

namespace Pollora\Support\Facades;

use Illuminate\Support\Facades\Facade;
use Pollora\Hook\Hook;

/**
 * Class Filter
 *
 * Facade for the Filter class, providing convenient static access.
 *
 * @method static void add(string $hook, callable|string|array $callback, int $priority = 10, ?int $acceptedArgs = null)
 * @method static void remove(string $hook, callable|string|array $callback, int $priority = 10)
 * @method static bool exists(string $hook, callable|string|array $callback = null, int $priority = null)
 * @method static mixed apply(string $hook, $value, ...$args)
 * @method static mixed applyArray(string $hook, $value, array $args = [])
 * @method static array getCallbacks(string $hook, ?int $priority = null)
 *
 * @see \Pollora\Hook\Filter
 * @package Pollora\Hook\Facades
 */
class Filter extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return \Pollora\Hook\Filter::class;
    }
}
