<?php

declare(strict_types=1);

namespace Pollora\Asset\Infrastructure\Laravel;

use Illuminate\Contracts\Container\Container;
use Pollora\Asset\Application\Services\AssetManager;
use Pollora\Asset\Domain\Contracts\ViteManagerFactory;
use Pollora\Asset\Infrastructure\Repositories\AssetContainer;
use Psr\Log\LoggerInterface;

/**
 * Turns the file of an AssetFile into its URL, through the container it names.
 *
 * An unknown container, or any error while resolving, gives an empty string and a line in the log.
 */
final readonly class LaravelAssetFileResolver
{
    public function __construct(private Container $app) {}

    public function __invoke(string $file, string $container): string
    {
        try {
            $assetContainer = $this->app->make(AssetManager::class)->getContainer($container);

            if (! $assetContainer instanceof AssetContainer) {
                return '';
            }

            return $this->app->make(ViteManagerFactory::class)->make($assetContainer)->asset($file);
        } catch (\Throwable $throwable) {
            $this->app->make(LoggerInterface::class)->error('Error in AssetFile::__toString', [
                'error' => $throwable->getMessage(),
                'path' => $file,
            ]);

            return '';
        }
    }
}
