<?php

declare(strict_types=1);

namespace Pollora\Schedule;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Orchestra\Testbench\TestCase;
use Pollora\Discovery\Domain\Contracts\DiscoveryEngineInterface;
use Pollora\Hook\Domain\Contracts\Filter;
use Pollora\Schedule\Contracts\SchedulerInterface;
use Pollora\Schedule\Events\RecurringEvent;
use Pollora\Schedule\Infrastructure\Services\ScheduleDiscovery;

/**
 * Service provider for WordPress cron scheduler functionality.
 *
 * Registers and bootstraps the scheduler services, including filters
 * and recurring event scheduling.
 */
class SchedulerServiceProvider extends ServiceProvider
{
    /**
     * Register scheduler services.
     */
    public function register(): void
    {
        $this->app->singleton(SchedulerInterface::class, Scheduler::class);
        $this->app->singleton(ScheduleDiscovery::class, fn (): ScheduleDiscovery => new ScheduleDiscovery);
    }

    /**
     * Bootstrap scheduler services.
     */
    public function boot(): void
    {
        if (config('wordpress.use_laravel_scheduler', false)) {
            $this->registerFilters($this->app->make(SchedulerInterface::class));
        }

        $this->registerScheduleDiscovery();

        $this->app->booted(function (): void {
            $this->scheduleRecurringEvents();
        });
    }

    /**
     * Register WordPress filters for the scheduler.
     *
     * @param  SchedulerInterface  $scheduler  Scheduler instance
     */
    protected function registerFilters(SchedulerInterface $scheduler): void
    {
        /** @var Filter $filter */
        $filter = $this->app->make(Filter::class);
        $filters = [
            'pre_get_scheduled_event' => 'preGetScheduledEvent',
            'pre_get_ready_cron_jobs' => 'preGetReadyCronJobs',
        ];

        foreach ($filters as $hook => $method) {
            $filter->add($hook, [$scheduler, $method], 10, 5);
        }

        $filters = [
            'pre_update_option_cron' => 'preUpdateOptionCron',
            'pre_option_cron' => 'preOptionCron',
            'pre_schedule_event' => 'preScheduleEvent',
            'pre_reschedule_event' => 'preRescheduleEvent',
            'pre_unschedule_event' => 'preUnscheduleEvent',
            'pre_clear_scheduled_hook' => 'preClearScheduledHook',
            'pre_unschedule_hook' => 'preUnscheduleHook',
        ];

        foreach ($filters as $hook => $method) {
            $filter->add($hook, [$scheduler, $method], 10, 5);
        }
    }

    /**
     * Schedule all recurring events.
     */
    protected function scheduleRecurringEvents(): void
    {
        if ($this->isOrchastraTest() || defined('WP_CLI')) {
            return;
        }

        $schedule = $this->app->make(Schedule::class);
        RecurringEvent::scheduleAllEvents($schedule);
    }

    /**
     * Register Schedule discovery with the discovery engine.
     */
    private function registerScheduleDiscovery(): void
    {
        if ($this->app->bound(DiscoveryEngineInterface::class)) {
            $engine = $this->app->make(DiscoveryEngineInterface::class);
            $engine->addDiscovery('schedules', $this->app->make(ScheduleDiscovery::class));
        }
    }

    /**
     * Check if we're running in an Orchestra test environment.
     */
    private function isOrchastraTest(): bool
    {
        return defined('LARAVEL_START') && class_exists(TestCase::class);
    }
}
