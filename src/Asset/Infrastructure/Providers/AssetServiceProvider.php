<?php

declare(strict_types=1);

namespace Pollora\Asset\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Pollora\Asset\Application\Services\AssetManager;
use Pollora\Asset\Application\Services\AssetRegistrationService;
use Pollora\Asset\Application\Services\AssetRetrievalService;
use Pollora\Asset\Domain\Contracts\AssetRepositoryInterface;
use Pollora\Asset\Infrastructure\Repositories\InMemoryAssetRepository;
use Pollora\Asset\Infrastructure\Services\AssetEnqueuer;
use Pollora\Console\Application\Services\ConsoleDetectionService;


/**
 * Laravel service provider for asset management services and bindings.
 *
 * This provider is responsible for binding the asset repository interface to its
 * infrastructure implementation, and for registering the AssetManager as a singleton
 * in the service container. It ensures that asset-related services are available
 * throughout the application.
 */
class AssetServiceProvider extends ServiceProvider
{
    /**
     * Register asset-related services and bindings in the container.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(AssetRepositoryInterface::class, InMemoryAssetRepository::class);
        $this->app->singleton(AssetManager::class, function ($app) {
            return new AssetManager(
                $app->make(AssetRegistrationService::class),
                $app->make(AssetRetrievalService::class)
            );
        });
        $this->app->bind(AssetEnqueuer::class, function ($app) {
            return new AssetEnqueuer($app);
        });
    }
}
