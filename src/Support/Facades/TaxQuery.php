<?php

declare(strict_types=1);

namespace Pollora\Support\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Facade for {@link \Pollora\Query\TaxQuery}.
 *
 * @see \Pollora\Query\TaxQuery
 *
 * @mixin \Pollora\Query\TaxQuery
 */
class TaxQuery extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'wp.query.taxonomy';
    }
}
