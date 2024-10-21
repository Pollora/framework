<?php

declare(strict_types=1);

namespace Pollora\Support\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Facade for {@link \Pollora\Query\MetaQuery}.
 *
 * @see \Pollora\Query\MetaQuery
 *
 * @mixin \Pollora\Query\MetaQuery
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
