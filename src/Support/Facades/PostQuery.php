<?php

declare(strict_types=1);

namespace Pollora\Support\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Facade for {@link \Pollora\Query\PostQuery}.
 *
 * @see \Pollora\Query\PostQuery
 *
 * @mixin \Pollora\Query\PostQuery
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
