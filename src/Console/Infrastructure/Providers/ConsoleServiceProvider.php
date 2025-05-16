<?php

namespace Pollora\Console\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Pollora\Console\Domain\Contracts\ConsoleDetectorInterface;
use Pollora\Console\Infrastructure\Services\LaravelConsoleDetector;

/**
 * Service provider for binding console detection interfaces to implementations.
 */
class ConsoleServiceProvider extends ServiceProvider
{
    /**
     * Register bindings in the container.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton(ConsoleDetectorInterface::class, function ($app) {
            return new LaravelConsoleDetector($app);
        });
    }
}
