<?php

declare(strict_types=1);

namespace Pollen\Support\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Facade for {@link \Pollen\Query\MetaQuery}.
 *
 * @see \Pollen\Query\MetaQuery
 *
 * @mixin \Pollen\Query\MetaQuery
 */
class MetaQuery extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'wp.query.meta';
    }
}
