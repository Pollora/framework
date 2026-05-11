<?php

declare(strict_types=1);

use Pollora\Discovery\Domain\Contracts\DiscoveryEngineInterface;
use Pollora\Hook\Domain\Contracts\Filter;
use Pollora\Schedule\Application\UseCases\RegisterScheduleDiscoveryUseCase;
use Pollora\Schedule\Application\UseCases\RegisterSchedulerFiltersUseCase;
use Pollora\Schedule\Contracts\SchedulerInterface;
use Pollora\Schedule\Infrastructure\Services\ScheduleDiscovery;
use Pollora\Schedule\Scheduler;
use Pollora\Schedule\SchedulerServiceProvider;

beforeEach(function (): void {
    // Mock dependencies
    $this->filter = Mockery::mock(Filter::class);
    $this->app->instance(Filter::class, $this->filter);

    $this->discoveryEngine = Mockery::mock(DiscoveryEngineInterface::class);
    $this->discoveryEngine->shouldReceive('addDiscovery')->byDefault();
    $this->app->instance(DiscoveryEngineInterface::class, $this->discoveryEngine);

    // Only test register() to avoid database/booted callback issues
    $this->provider = new SchedulerServiceProvider($this->app);
    $this->provider->register();
});

describe('SchedulerServiceProvider', function (): void {
    it('binds SchedulerInterface as singleton', function (): void {
        $scheduler = $this->app->make(SchedulerInterface::class);

        expect($scheduler)->toBeInstanceOf(Scheduler::class);
        expect($this->app->make(SchedulerInterface::class))->toBe($scheduler);
    });

    it('binds ScheduleDiscovery as singleton', function (): void {
        $discovery = $this->app->make(ScheduleDiscovery::class);

        expect($discovery)->toBeInstanceOf(ScheduleDiscovery::class);
        expect($this->app->make(ScheduleDiscovery::class))->toBe($discovery);
    });

    it('binds RegisterSchedulerFiltersUseCase', function (): void {
        $useCase = $this->app->make(RegisterSchedulerFiltersUseCase::class);

        expect($useCase)->toBeInstanceOf(RegisterSchedulerFiltersUseCase::class);
    });

    it('binds RegisterScheduleDiscoveryUseCase', function (): void {
        $useCase = $this->app->make(RegisterScheduleDiscoveryUseCase::class);

        expect($useCase)->toBeInstanceOf(RegisterScheduleDiscoveryUseCase::class);
    });
});
