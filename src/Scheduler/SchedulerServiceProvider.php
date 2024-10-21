<?php

declare(strict_types=1);

namespace Pollora\Scheduler;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Pollora\Scheduler\Contracts\SchedulerInterface;
use Pollora\Support\Facades\Filter;

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
        $this->app->booted(function (): void {
            $this->scheduleRecurringEvents();
        });
    }

    protected function registerFilters(SchedulerInterface $scheduler): void
    {
        if ($this->isOrchastraTest()) {
            return;
        }

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
        if ($this->isOrchastraTest() || defined('WP_CLI')) {
            return;
        }

        $schedule = $this->app->make(Schedule::class);
        \Pollora\Scheduler\Events\RecurringEvent::scheduleAllEvents($schedule);
    }

    protected function isOrchastraTest(): bool
    {
        $db = DB::getConfig(null);

        return str_contains((string) $db['database'], '/orchestra/');
    }
}
