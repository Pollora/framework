<?php

declare(strict_types=1);

use Mockery as m;
use Pollora\Discovery\Domain\Contracts\DiscoveryEngineInterface;
use Pollora\Schedule\Application\UseCases\RegisterScheduleDiscoveryUseCase;
use Pollora\Schedule\Infrastructure\Services\ScheduleDiscovery;

beforeEach(function (): void {
    $this->discoveryEngine = m::mock(DiscoveryEngineInterface::class);
    $this->scheduleDiscovery = new ScheduleDiscovery;
    $this->useCase = new RegisterScheduleDiscoveryUseCase(
        $this->discoveryEngine,
        $this->scheduleDiscovery
    );
});

describe('RegisterScheduleDiscoveryUseCase', function (): void {

    it('registers schedule discovery with the discovery engine', function (): void {
        $this->discoveryEngine->shouldReceive('addDiscovery')
            ->once()
            ->with('schedules', $this->scheduleDiscovery);

        $this->useCase->execute();
    });

    it('passes the ScheduleDiscovery instance to the engine', function (): void {
        $this->discoveryEngine->shouldReceive('addDiscovery')
            ->once()
            ->with('schedules', m::on(fn ($arg): bool => $arg instanceof ScheduleDiscovery));

        $this->useCase->execute();
    });
});
