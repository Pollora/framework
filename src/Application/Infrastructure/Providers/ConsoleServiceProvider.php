<?php

declare(strict_types=1);

namespace Pollora\Application\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Pollora\Application\Domain\Contracts\ConsoleDetectorInterface;
use Pollora\Application\Infrastructure\Services\LaravelConsoleDetector;

/**
 * Service provider for binding console detection interfaces to implementations.
 */
class ConsoleServiceProvider extends ServiceProvider
{
    /**
     * Register bindings in the container.
     */
    public function register(): void
    {
        $this->app->singleton(ConsoleDetectorInterface::class, fn ($app): \Pollora\Application\Infrastructure\Services\LaravelConsoleDetector => new LaravelConsoleDetector($app));
    }
}
