<?php

declare(strict_types=1);

namespace Pollen\Support\Facades;

use Illuminate\Support\Facades\Facade;
use Pollen\Asset as AssetBuilder;

/**
 * @method static AssetBuilder add($handle, $file)
 */
class Asset extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'wp.asset';
    }
}
