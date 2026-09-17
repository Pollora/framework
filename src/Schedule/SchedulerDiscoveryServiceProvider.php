<?php

declare(strict_types=1);

namespace Pollora\Schedule;

use Illuminate\Support\ServiceProvider;
use Pollora\Schedule\Infrastructure\Services\ScheduleDiscovery;

/**
 * Service provider for schedule discovery functionality only.
 *
 * A lighter alternative to SchedulerServiceProvider when only
 * schedule attribute discovery is needed (no cron filter registration).
 * The DiscoveryRegistrar auto-detects and registers ScheduleDiscovery.
 */
class SchedulerDiscoveryServiceProvider extends ServiceProvider
{
    /**
     * Register scheduler services.
     */
    public function register(): void
    {
        $this->app->singleton(ScheduleDiscovery::class, fn (): ScheduleDiscovery => new ScheduleDiscovery);
    }
}
