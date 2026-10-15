<?php

declare(strict_types=1);

namespace Pollora\Asset\Infrastructure\Providers;

use Closure;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Pollora\Application\Application\Services\ConsoleDetectionService;
use Pollora\Asset\Application\Services\AssetManager;
use Pollora\Asset\Application\Services\AssetRetrievalService;
use Pollora\Asset\Application\Services\MissingBuilds;
use Pollora\Asset\Domain\Contracts\AssetRepositoryInterface;
use Pollora\Asset\Domain\Contracts\PublicPath;
use Pollora\Asset\Domain\Contracts\RuntimeContext;
use Pollora\Asset\Domain\Contracts\ViteManagerFactory;
use Pollora\Asset\Infrastructure\Laravel\LaravelAssetFileResolver;
use Pollora\Asset\Infrastructure\Laravel\LaravelPublicPath;
use Pollora\Asset\Infrastructure\Laravel\LaravelRuntimeContext;
use Pollora\Asset\Infrastructure\Laravel\LaravelViteManagerFactory;
use Pollora\Asset\Infrastructure\Repositories\InMemoryAssetRepository;
use Pollora\Asset\Infrastructure\Services\AssetEnqueuer;
use Pollora\Asset\Infrastructure\Services\AssetFile;
use Pollora\Asset\Infrastructure\Services\RootAssetManager;
use Pollora\Asset\UI\Http\MissingBuildNotice;
use Pollora\Hook\Domain\Contract\Action;
use Pollora\Hook\Domain\Contract\Filter;
use Psr\Log\LoggerInterface;

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
     */
    public function register(): void
    {
        $this->app->bind(AssetRepositoryInterface::class, InMemoryAssetRepository::class);

        // What the assets need from the environment: here, Laravel
        $this->app->singleton(PublicPath::class, LaravelPublicPath::class);
        $this->app->singleton(ViteManagerFactory::class, LaravelViteManagerFactory::class);
        $this->app->singleton(RuntimeContext::class, fn (Application $app): RuntimeContext => new LaravelRuntimeContext($app->make(ConsoleDetectionService::class)));

        $this->app->singleton(AssetManager::class, fn (Application $app): AssetManager => new AssetManager(
            $app->make(AssetRetrievalService::class),
            $app->make(PublicPath::class),
            static fn (): AssetEnqueuer => $app->make(AssetEnqueuer::class),
        ));
        // Hooks come from pollora/hook's provider, absent from some test applications
        $this->app->bind(AssetEnqueuer::class, fn (Application $app): AssetEnqueuer => new AssetEnqueuer(
            $app->make(AssetManager::class),
            $app->bound(Action::class) ? $app->make(Action::class) : null,
            $app->bound(Filter::class) ? $app->make(Filter::class) : null,
            $app->make(ViteManagerFactory::class),
            $app->make(RuntimeContext::class),
            $app->make(LoggerInterface::class),
        ));
        AssetFile::resolveUsing(Closure::fromCallable(new LaravelAssetFileResolver($this->app)));
        $this->app->bind(MissingBuildNotice::class, fn (Application $app): MissingBuildNotice => new MissingBuildNotice($app->make(MissingBuilds::class), base_path()));
        $this->app->singleton(MissingBuilds::class);
        $this->app->singleton(RootAssetManager::class, fn (Application $app): RootAssetManager => new RootAssetManager(
            $app->make(AssetManager::class)
        ));
    }

    /**
     * Bootstrap services after all providers have been registered.
     */
    public function boot(): void
    {
        $this->app->make(RootAssetManager::class)->registerRootAssets();

        // Hooks come from pollora/hook's provider, absent from some test applications
        if ($this->app->bound(Action::class)) {
            $this->app->make(Action::class)->add('admin_notices', [$this->app->make(MissingBuildNotice::class), 'render']);
        }
    }
}
