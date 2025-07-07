<?php

declare(strict_types=1);

namespace Pollora\Schedule;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Pollora\Discovery\Domain\Contracts\DiscoveryEngineInterface;
use Pollora\Schedule\Infrastructure\Services\ScheduleDiscovery;

/**
 * Service provider for WordPress cron scheduler functionality.
 *
 * Registers and bootstraps the scheduler services, including filters
 * and recurring event scheduling.
 */
class SchedulerDiscoveryServiceProvider extends ServiceProvider
{
    /**
     * Register scheduler services.
     */
    public function register(): void
    {
        // Register Schedule Discovery
        $this->app->singleton(ScheduleDiscovery::class, fn (): \Pollora\Schedule\Infrastructure\Services\ScheduleDiscovery => new ScheduleDiscovery);
    }

    /**
     * Bootstrap scheduler services.
     */
    public function boot(): void
    {
        // Register Schedule discovery with the discovery engine
        $this->registerScheduleDiscovery();
    }

    /**
     * Register Schedule discovery with the discovery engine.
     */
    private function registerScheduleDiscovery(): void
    {
        if ($this->app->bound(DiscoveryEngineInterface::class)) {
            /** @var DiscoveryEngineInterface $engine */
            $engine = $this->app->make(DiscoveryEngineInterface::class);
            $scheduleDiscovery = $this->app->make(ScheduleDiscovery::class);

            $engine->addDiscovery('schedules', $scheduleDiscovery);
        }
    }
}
