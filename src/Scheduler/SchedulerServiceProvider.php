<?php

namespace Pollen\Scheduler;

use Illuminate\Support\ServiceProvider;
use Pollen\Scheduler\Contracts\SchedulerInterface;
use Pollen\Scheduler\Scheduler;
use Pollen\Support\Facades\Action;
use Pollen\Support\Facades\Filter;
use Illuminate\Console\Scheduling\Schedule;

class SchedulerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SchedulerInterface::class, Scheduler::class);

        $scheduler = $this->app->make(SchedulerInterface::class);

        $this->registerFilters($scheduler);
    }

    public function boot(): void
    {
        $this->app->booted(function () {
            $this->scheduleRecurringEvents();
        });
    }

    protected function registerFilters(SchedulerInterface $scheduler): void
    {
        $filters = [
            'pre_update_option_cron' => 'preUpdateOptionCron',
            'pre_option_cron' => 'preOptionCron',
            'pre_schedule_event' => 'preScheduleEvent',
            'pre_reschedule_event' => 'preRescheduleEvent',
            'pre_unschedule_event' => 'preUnscheduleEvent',
            'pre_clear_scheduled_hook' => 'preClearScheduledHook',
            'pre_unschedule_hook' => 'preUnscheduleHook',
            'pre_get_scheduled_event' => 'preGetScheduledEvent',
            'pre_get_ready_cron_jobs' => 'preGetReadyCronJobs',
        ];

        foreach ($filters as $hook => $method) {
            Filter::add($hook, [$scheduler, $method], 10, 5);
        }
    }

    protected function scheduleRecurringEvents(): void
    {
        if (defined('WP_CLI')) {
            return;
        }
        $schedule = $this->app->make(Schedule::class);
        \Pollen\Scheduler\Events\RecurringEvent::scheduleAllEvents($schedule);
    }
}
