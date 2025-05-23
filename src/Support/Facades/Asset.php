<?php

declare(strict_types=1);

namespace Pollora\Support\Facades;

use Illuminate\Support\Facades\Facade;
use Pollora\Asset\Application\Services\AssetManager;

/**
 * Facade for WordPress Asset Management.
 *
 * Provides methods for registering and enqueueing WordPress scripts and styles
 * with improved dependency management.
 *
 * @method static AssetManager add(string $handle, string $file) Register a new asset
 *
 * @see AssetManager
 */
class Asset extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return AssetManager::class;
    }
}
