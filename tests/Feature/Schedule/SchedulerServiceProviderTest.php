<?php

declare(strict_types=1);

use Pollora\Discovery\Domain\Contracts\DiscoveryEngineInterface;
use Pollora\Hook\Domain\Contract\Filter;
use Pollora\Schedule\Infrastructure\Services\ScheduleDiscovery;
use Pollora\Schedule\Schedule;
use Pollora\Schedule\SchedulerServiceProvider;

beforeEach(function (): void {
    $this->filter = Mockery::mock(Filter::class);
    $this->app->instance(Filter::class, $this->filter);

    $this->discoveryEngine = Mockery::mock(DiscoveryEngineInterface::class);
    $this->app->instance(DiscoveryEngineInterface::class, $this->discoveryEngine);

    $this->provider = new SchedulerServiceProvider($this->app);
    $this->provider->register();
});

describe('SchedulerServiceProvider', function (): void {
    it('binds ScheduleDiscovery as singleton', function (): void {
        $discovery = $this->app->make(ScheduleDiscovery::class);

        expect($discovery)->toBeInstanceOf(ScheduleDiscovery::class);
        expect($this->app->make(ScheduleDiscovery::class))->toBe($discovery);
    });

    it('gives the schedule engine the application, so the classes passed to call() get their dependencies', function (): void {
        $container = (new ReflectionProperty(Schedule::engine(), 'container'))->getValue(Schedule::engine());

        expect($container)->toBe($this->app);
    });
});
