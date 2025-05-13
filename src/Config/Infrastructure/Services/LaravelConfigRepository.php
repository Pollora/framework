<?php

declare(strict_types=1);

namespace Pollora\Config\Infrastructure\Services;

use Illuminate\Support\Facades\Config;
use Pollora\Config\Domain\Contracts\ConfigRepositoryInterface;

/**
 * Laravel implementation of the ConfigRepositoryInterface.
 */
class LaravelConfigRepository implements ConfigRepositoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function get(string $key, mixed $default = null)
    {
        return Config::get($key, $default);
    }
} 