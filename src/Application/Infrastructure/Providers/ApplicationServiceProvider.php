<?php

declare(strict_types=1);

namespace Pollora\Application\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Pollora\Application\Domain\Contracts\ConsoleDetectorInterface;
use Pollora\Application\Domain\Contracts\DebugDetectorInterface;
use Pollora\Application\Infrastructure\Services\LaravelConsoleDetector;
use Pollora\Application\Infrastructure\Services\LaravelDebugDetector;

/**
 * Service provider for application detection services (console, debug).
 */
class ApplicationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ConsoleDetectorInterface::class, fn ($app): LaravelConsoleDetector => new LaravelConsoleDetector($app));
        $this->app->singleton(DebugDetectorInterface::class, fn ($app): LaravelDebugDetector => new LaravelDebugDetector($app));
    }
}