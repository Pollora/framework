<?php

declare(strict_types=1);

namespace Pollora\Support\Facades;

use Illuminate\Support\Facades\Facade;
use Pollora\Ajax\Ajax as AjaxBuilder;

/**
 * @method static AjaxBuilder listen(string $action, callable|string $callback)
 */
class Ajax extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'wp.ajax';
    }
}
