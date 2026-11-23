<?php

declare(strict_types=1);

namespace Pollora\Support\Facades;

use Illuminate\Support\Facades\Facade;
use Pollora\Option\Domain\Contracts\OptionManagerInterface;

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
        return OptionManagerInterface::class;
    }
}
