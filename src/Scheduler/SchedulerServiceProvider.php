<?php

namespace Pollen\Scheduler;

use Illuminate\Support\ServiceProvider;
use Pollen\Scheduler\WpScheduler;
use Pollen\Support\Facades\Action;
use Pollen\Support\Facades\Filter;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;

class SchedulerServiceProvider extends ServiceProvider
{
    public $scheduler;

    public function register(): void
    {
        $this->app->singleton(WpScheduler::class);

        $this->scheduler = $this->app->make(WpScheduler::class);

        Filter::add('pre_update_option_cron', [$this->scheduler, 'preUpdateOptionCron'], 10, 2);
        Filter::add('pre_update_option_cron', [$this->scheduler, 'preUpdateOptionCron'], 10, 2);
        Filter::add('pre_option_cron', [$this->scheduler, 'preOptionCron']);
        Filter::add('pre_schedule_event', [$this->scheduler, 'preScheduleEvent'], 10, 4);
        Filter::add('pre_reschedule_event', [$this->scheduler, 'preRescheduleEvent'], 10, 3);
        Filter::add('pre_unschedule_event', [$this->scheduler, 'preUnscheduleEvent'], 10, 5);
        Filter::add('pre_clear_scheduled_hook', [$this->scheduler, 'preClearScheduledHook'], 10, 4);
        Filter::add('pre_unschedule_hook', [$this->scheduler, 'preUnscheduleHook'], 10, 3);
        Filter::add('pre_get_scheduled_event', [$this->scheduler, 'preGetScheduledEvent'], 10, 4);
        Filter::add('pre_get_ready_cron_jobs', [$this->scheduler, 'preGetReadyCronJobs']);
    }

    public function boot(): void
    {
        $this->app->booted(function () {
           $this->scheduleRecurringEvents();
        });
    }

    protected function scheduleRecurringEvents()
    {
        if (defined('WP_CLI')) {
            return;
        }
        $schedule = $this->app->make(Schedule::class);
        WordPressRecurringEvent::scheduleAllEvents($schedule);
    }
}
