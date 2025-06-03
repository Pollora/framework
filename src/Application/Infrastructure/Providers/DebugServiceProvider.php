<?php

declare(strict_types=1);

namespace Pollora\Application\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Pollora\Application\Domain\Contracts\DebugDetectorInterface;
use Pollora\Application\Infrastructure\Services\LaravelDebugDetector;

/**
 * Service provider for debug detection.
 */
class DebugServiceProvider extends ServiceProvider
{
    /**
     * Register debug detector services.
     */
    public function register(): void
    {
        $this->app->singleton(DebugDetectorInterface::class, function ($app) {
            return new LaravelDebugDetector($app);
        });
    }
}
