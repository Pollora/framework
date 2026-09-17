<?php

declare(strict_types=1);

namespace Pollora\Schedule\Application\UseCases;

use Pollora\Hook\Domain\Contract\Filter;
use Pollora\Schedule\Contracts\SchedulerInterface;

/**
 * Use case for registering WordPress cron filters on the scheduler.
 *
 * Registers all WordPress filter hooks that intercept cron operations
 * and delegate them to the Laravel-based scheduler implementation.
 */
class RegisterSchedulerFiltersUseCase
{
    public function __construct(
        private readonly Filter $filter,
        private readonly SchedulerInterface $scheduler
    ) {}

    /**
     * Execute the use case to register all scheduler filters.
     */
    public function execute(): void
    {
        $this->registerEventQueryFilters();
        $this->registerEventManagementFilters();
    }

    /**
     * Register filters for querying scheduled events.
     */
    private function registerEventQueryFilters(): void
    {
        $filters = [
            'pre_get_scheduled_event' => 'preGetScheduledEvent',
            'pre_get_ready_cron_jobs' => 'preGetReadyCronJobs',
        ];

        foreach ($filters as $hook => $method) {
            $this->filter->add($hook, [$this->scheduler, $method], 10, 5);
        }
    }

    /**
     * Register filters for managing scheduled events.
     */
    private function registerEventManagementFilters(): void
    {
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
            $this->filter->add($hook, [$this->scheduler, $method], 10, 5);
        }
    }
}
