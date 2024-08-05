<?php

declare(strict_types=1);

namespace Pollen\Support\Facades;

use Illuminate\Support\Facades\Facade;
use Pollen\Ajax\Ajax as AjaxBuilder;

/**
 * @method static AjaxBuilder listen(string $action, callable|string $callback)
 */
class Ajax extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'wp.ajax';
    }
}
