<?php

declare(strict_types=1);

namespace Pollora\Support\Facades;

use Illuminate\Support\Facades\Facade;
use Pollora\Asset as AssetBuilder;

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
