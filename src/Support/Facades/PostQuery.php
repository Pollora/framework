<?php

declare(strict_types=1);

namespace Pollen\Support\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Facade for {@link \Pollen\Query\PostQuery}.
 *
 * @see \Pollen\Query\PostQuery
 *
 * @mixin \Pollen\Query\PostQuery
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
