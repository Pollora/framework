<?php

declare(strict_types=1);

namespace Pollora\Schedule;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Pollora\Discovery\Domain\Contracts\DiscoveryEngineInterface;
use Pollora\Schedule\Application\UseCases\RegisterScheduleDiscoveryUseCase;
use Pollora\Schedule\Infrastructure\Services\ScheduleDiscovery;

/**
 * Service provider for schedule discovery functionality only.
 *
 * A lighter alternative to SchedulerServiceProvider when only
 * schedule attribute discovery is needed (no cron filter registration).
 */
class SchedulerDiscoveryServiceProvider extends ServiceProvider
{
    /**
     * Register scheduler services.
     */
    public function register(): void
    {
        $this->app->singleton(ScheduleDiscovery::class, fn (): ScheduleDiscovery => new ScheduleDiscovery);

        $this->app->bind(RegisterScheduleDiscoveryUseCase::class, fn (Application $app): RegisterScheduleDiscoveryUseCase => new RegisterScheduleDiscoveryUseCase(
            $app->make(DiscoveryEngineInterface::class),
            $app->make(ScheduleDiscovery::class)
        ));
    }

    /**
     * Bootstrap scheduler services.
     */
    public function boot(): void
    {
        if ($this->app->bound(DiscoveryEngineInterface::class)) {
            $this->app->make(RegisterScheduleDiscoveryUseCase::class)->execute();
        }
    }
}
