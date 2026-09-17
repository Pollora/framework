<?php

declare(strict_types=1);

use Mockery as m;
use Pollora\Hook\Domain\Contract\Filter;
use Pollora\Schedule\Application\UseCases\RegisterSchedulerFiltersUseCase;
use Pollora\Schedule\Contracts\SchedulerInterface;

beforeEach(function (): void {
    $this->filter = m::mock(Filter::class);
    $this->scheduler = m::mock(SchedulerInterface::class);
    $this->useCase = new RegisterSchedulerFiltersUseCase($this->filter, $this->scheduler);
});

describe('RegisterSchedulerFiltersUseCase', function (): void {

    it('registers all nine WordPress cron filters', function (): void {
        $this->filter->shouldReceive('add')->times(9);

        $this->useCase->execute();
    });

    it('registers event query filters with correct hooks', function (): void {
        $scheduler = $this->scheduler;

        $this->filter->shouldReceive('add')
            ->with('pre_get_scheduled_event', [$scheduler, 'preGetScheduledEvent'], 10, 5)
            ->once();
        $this->filter->shouldReceive('add')
            ->with('pre_get_ready_cron_jobs', [$scheduler, 'preGetReadyCronJobs'], 10, 5)
            ->once();

        // Allow the management filters
        $this->filter->shouldReceive('add')->times(7);

        $this->useCase->execute();
    });

    it('registers event management filters with correct hooks', function (): void {
        $scheduler = $this->scheduler;

        $expectedManagementFilters = [
            'pre_update_option_cron' => 'preUpdateOptionCron',
            'pre_option_cron' => 'preOptionCron',
            'pre_schedule_event' => 'preScheduleEvent',
            'pre_reschedule_event' => 'preRescheduleEvent',
            'pre_unschedule_event' => 'preUnscheduleEvent',
            'pre_clear_scheduled_hook' => 'preClearScheduledHook',
            'pre_unschedule_hook' => 'preUnscheduleHook',
        ];

        foreach ($expectedManagementFilters as $hook => $method) {
            $this->filter->shouldReceive('add')
                ->with($hook, [$scheduler, $method], 10, 5)
                ->once();
        }

        // Allow the query filters
        $this->filter->shouldReceive('add')->times(2);

        $this->useCase->execute();
    });

    it('registers all filters with priority 10 and 5 accepted args', function (): void {
        $this->filter->shouldReceive('add')
            ->with(m::type('string'), m::type('array'), 10, 5)
            ->times(9);

        $this->useCase->execute();
    });
});
