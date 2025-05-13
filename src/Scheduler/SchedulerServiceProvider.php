<?php

declare(strict_types=1);

namespace Pollora\Scheduler;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Pollora\Container\Domain\ServiceLocator;
use Pollora\Hook\Infrastructure\Services\Filter;
use Pollora\Scheduler\Contracts\SchedulerInterface;

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

        $scheduler = $this->app->make(SchedulerInterface::class);

        $this->registerFilters($scheduler);
    }

    /**
     * Bootstrap scheduler services.
     */
    public function boot(): void
    {
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
        /** @var ServiceLocator $locator */
        $locator = $this->app->make(ServiceLocator::class);

        /** @var Filter $filter */
        $filter = $locator->resolve(Filter::class);
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
        \Pollora\Scheduler\Events\RecurringEvent::scheduleAllEvents($schedule);
    }
}
