<?php

declare(strict_types=1);

namespace Pollora\Schedule;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\QueryException;
use Illuminate\Support\ServiceProvider;
use Orchestra\Testbench\TestCase;
use Pollora\Doctor\Infrastructure\Providers\DoctorServiceProvider;
use Pollora\Hook\Domain\Contract\Filter;
use Pollora\Schedule\Application\Services\ScheduleStatus;
use Pollora\Schedule\Application\UseCases\RegisterSchedulerFiltersUseCase;
use Pollora\Schedule\Contracts\SchedulerInterface;
use Pollora\Schedule\Events\RecurringEvent;
use Pollora\Schedule\Infrastructure\Checks\ScheduleDriverCheck;
use Pollora\Schedule\Infrastructure\Services\MigrationNotice;
use Pollora\Schedule\Infrastructure\Services\ScheduleDiscovery;
use Pollora\Schedule\Schedule as ScheduleApi;
use Pollora\Schedule\UI\Console\ScheduleListCommand;
use Pollora\Schedule\UI\Console\ScheduleMigrateCommand;

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

        $this->mergeConfigFrom(__DIR__.'/../../config/schedule.php', 'schedule');
        $this->app->singleton(ScheduleStatus::class, fn (Application $app): ScheduleStatus => new ScheduleStatus($app->make(Repository::class)));

        ScheduleApi::useContainer($this->app);
        $this->chooseDriver($this->app->make(Repository::class));

        if ($this->app->runningInConsole()) {
            $this->commands([ScheduleListCommand::class, ScheduleMigrateCommand::class]);
        }
    }

    /**
     * Bootstrap scheduler services.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../../config/schedule.php' => config_path('schedule.php'),
        ], 'pollora-schedule');

        // A check of pollora:doctor and Site Health; tagged on boot, so it comes after the framework's own
        $this->app->tag([ScheduleDriverCheck::class], DoctorServiceProvider::CHECKS_TAG);
        $this->app->make(MigrationNotice::class)->register();

        if (config('wordpress.use_laravel_scheduler', false)) {
            $this->app->make(RegisterSchedulerFiltersUseCase::class)->execute();
        }

        $this->app->booted(function (): void {
            // The driver was chosen when the application started; WordPress can take its hooks by now
            ScheduleApi::boot();
            $this->scheduleRecurringEvents();
        });
    }

    /**
     * Choose the driver of the site, WP-Cron when config/schedule.php names one that does not exist (pollora:doctor says so).
     */
    private function chooseDriver(Repository $config): void
    {
        $driver = $config->get('schedule.driver', 'wp-cron');

        try {
            ScheduleApi::driver(is_string($driver) && $driver !== '' ? $driver : 'wp-cron');
        } catch (\InvalidArgumentException) {
            ScheduleApi::driver('wp-cron');
        }

        $protected = $config->get('schedule.protected_hooks', []);
        ScheduleApi::engine()->protect(...array_map(strval(...), is_array($protected) ? $protected : []));
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
