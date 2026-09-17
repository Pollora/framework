<?php

declare(strict_types=1);

namespace Pollora\Schedule;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\QueryException;
use Illuminate\Support\ServiceProvider;
use Orchestra\Testbench\TestCase;
use Pollora\Hook\Domain\Contract\Filter;
use Pollora\Schedule\Application\UseCases\RegisterSchedulerFiltersUseCase;
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
        $this->registerDomainContracts();
        $this->registerUseCases();
    }

    /**
     * Bootstrap scheduler services.
     */
    public function boot(): void
    {
        if (config('wordpress.use_laravel_scheduler', false)) {
            $this->app->make(RegisterSchedulerFiltersUseCase::class)->execute();
        }

        $this->app->booted(function (): void {
            $this->scheduleRecurringEvents();
        });
    }

    /**
     * Register domain contracts with their infrastructure implementations.
     */
    private function registerDomainContracts(): void
    {
        $this->app->singleton(SchedulerInterface::class, Scheduler::class);
        $this->app->singleton(ScheduleDiscovery::class, fn (): ScheduleDiscovery => new ScheduleDiscovery);
    }

    /**
     * Register application use cases.
     */
    private function registerUseCases(): void
    {
        $this->app->bind(RegisterSchedulerFiltersUseCase::class, fn (Application $app): RegisterSchedulerFiltersUseCase => new RegisterSchedulerFiltersUseCase(
            $app->make(Filter::class),
            $app->make(SchedulerInterface::class)
        ));

    }

    /**
     * Schedule all recurring events.
     */
    protected function scheduleRecurringEvents(): void
    {
        if ($this->isOrchestraTest() || defined('WP_CLI')) {
            return;
        }

        try {
            $schedule = $this->app->make(Schedule::class);
            RecurringEvent::scheduleAllEvents($schedule);
        } catch (QueryException) {
            // WordPress tables may not exist yet during initial installation
        }
    }

    /**
     * Check if we're running in an Orchestra test environment.
     */
    private function isOrchestraTest(): bool
    {
        return defined('LARAVEL_START') && class_exists(TestCase::class);
    }
}
