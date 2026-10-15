<?php

declare(strict_types=1);

namespace Pollora\Support\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Facade for the WordPress network query (multisite).
 *
 * Provides a fluent interface for building the arguments of `WP_Network_Query`, and for running the query:
 * `first()`, `ids()`, `count()`, `exists()` and `get()`. Each use starts a fresh builder.
 *
 * @mixin \Pollora\Query\NetworkQuery
 *
 * @see \Pollora\Query\NetworkQuery
 */
class NetworkQuery extends Facade
{
    /**
     * A query builder keeps what it is given: the facade resolves a new one at each call instead of caching it.
     *
     * @var bool
     */
    protected static $cached = false;

    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'wp.query.network';
    }
}
