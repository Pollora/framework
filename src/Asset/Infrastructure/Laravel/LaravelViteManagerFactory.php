<?php

declare(strict_types=1);

namespace Pollora\Asset\Infrastructure\Laravel;

use Pollora\Asset\Domain\Contracts\ViteManagerFactory;
use Pollora\Asset\Domain\Contracts\ViteManagerInterface;
use Pollora\Asset\Infrastructure\Repositories\AssetContainer;
use Pollora\Asset\Infrastructure\Services\ViteManager;

/**
 * Resolves a container through Laravel's Vite.
 */
final class LaravelViteManagerFactory implements ViteManagerFactory
{
    public function make(AssetContainer $container): ViteManagerInterface
    {
        return new ViteManager($container);
    }
}
