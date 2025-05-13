<?php

declare(strict_types=1);

namespace Pollora\Gutenberg\Infrastructure\Services;

use Pollora\Gutenberg\Domain\Contracts\ConfigRepositoryInterface;

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
        return config($key, $default);
    }
}
