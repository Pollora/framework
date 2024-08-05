<?php

declare(strict_types=1);

namespace Pollen\Support\Facades;

use Illuminate\Support\Facades\Facade;
use Pollen\Asset\AssetFactory;
use Pollen\Asset as AssetBuilder;

/**
 * @method static AssetBuilder add($handle, $file)
 */
class Asset extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'wp.asset';
    }
}
