<?php

declare(strict_types=1);

namespace Pollora\Schedule\Application\UseCases;

use Pollora\Discovery\Domain\Contracts\DiscoveryEngineInterface;
use Pollora\Schedule\Infrastructure\Services\ScheduleDiscovery;

/**
 * Use case for registering schedule discovery with the discovery engine.
 *
 * Integrates the ScheduleDiscovery service with the framework's discovery
 * system so that #[Schedule] attributes are automatically discovered
 * and registered as WordPress cron events.
 */
class RegisterScheduleDiscoveryUseCase
{
    public function __construct(
        private readonly DiscoveryEngineInterface $discoveryEngine,
        private readonly ScheduleDiscovery $scheduleDiscovery
    ) {}

    /**
     * Execute the use case to register schedule discovery.
     */
    public function execute(): void
    {
        $this->discoveryEngine->addDiscovery('schedules', $this->scheduleDiscovery);
    }
}
