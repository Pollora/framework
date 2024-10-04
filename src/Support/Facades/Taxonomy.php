<?php

declare(strict_types=1);

namespace Pollen\Support\Facades;

use Illuminate\Support\Facades\Facade;

class Taxonomy extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'wp.taxonomy';
    }
}
