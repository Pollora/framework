<?php

declare(strict_types=1);

namespace Pollora\Support\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Facade for WordPress Post Query functionality.
 *
 * Provides a fluent interface for building WordPress post queries with
 * improved type safety and modern PHP syntax.
 *
 * @mixin \Pollora\Query\PostQuery
 *
 * @see \Pollora\Query\PostQuery
 */
class PostQuery extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'wp.query.post';
    }
}
