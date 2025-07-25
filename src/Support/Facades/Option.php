<?php

declare(strict_types=1);

namespace Pollora\Support\Facades;

use Illuminate\Support\Facades\Facade;
use Pollora\Option\Application\Services\OptionService;

/**
 * Facade for WordPress option management.
 *
 * @method static mixed get(string $key, mixed $default = null)
 * @method static bool set(string $key, mixed $value)
 * @method static bool update(string $key, mixed $value)
 * @method static bool delete(string $key)
 * @method static bool exists(string $key)
 * @method static bool forget(string $key)
 */
final class Option extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return OptionService::class;
    }

    /**
     * Laravel-style alias for delete method.
     *
     * @param  string  $key  The option key to remove
     * @return bool True on success, false on failure
     */
    public static function forget(string $key): bool
    {
        return self::delete($key);
    }
}
