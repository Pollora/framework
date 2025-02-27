<?php

declare(strict_types=1);

namespace Pollora\Support\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static void queue(string $key, mixed $value)
 * @method static mixed get(string $key)
 * @method static void apply()
 */
class Constant extends Facade
{
    /**
     * Get the registered name of the component
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'constant.manager';
    }
}
