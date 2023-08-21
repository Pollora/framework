<?php

declare(strict_types=1);

namespace Pollen\Support\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Facade for {@link Pollen\Proxy\Query} proxy. Provides access to the main query.
 *
 * @see \Pollen\Proxy\Query
 *
 * @mixin \Pollen\Proxy\Query
 *
 * @author Jordan Doyle <jordan@doyle.wf>
 */
class Query extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'wp.query';
    }
}
