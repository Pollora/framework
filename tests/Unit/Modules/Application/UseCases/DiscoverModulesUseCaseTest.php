<?php

declare(strict_types=1);

use Mockery as m;
use Pollora\Modules\Application\UseCases\DiscoverModulesUseCase;
use Pollora\Modules\Infrastructure\Services\ModuleDiscoveryOrchestrator;
use Psr\Log\LoggerInterface;

beforeEach(function (): void {
    $this->orchestrator = m::mock(ModuleDiscoveryOrchestrator::class);
    $this->logger = m::mock(LoggerInterface::class);
    $this->useCase = new DiscoverModulesUseCase($this->orchestrator, $this->logger);
});

describe('DiscoverModulesUseCase', function (): void {

    it('discovers both Laravel and Framework modules', function (): void {
        $this->orchestrator->shouldReceive('discoverLaravelModules')->once();
        $this->orchestrator->shouldReceive('discoverFrameworkModules')->once();

        $this->useCase->execute();
    });

    it('handles Laravel module discovery errors gracefully', function (): void {
        $exception = new RuntimeException('nwidart not installed');

        $this->orchestrator->shouldReceive('discoverLaravelModules')
            ->once()
            ->andThrow($exception);
        $this->orchestrator->shouldReceive('discoverFrameworkModules')->once();

        $this->logger->shouldReceive('error')
            ->once()
            ->with('Laravel Module discovery error', m::type('array'));

        $this->useCase->execute();
    });

    it('handles Framework module discovery errors gracefully', function (): void {
        $exception = new RuntimeException('app/ not found');

        $this->orchestrator->shouldReceive('discoverLaravelModules')->once();
        $this->orchestrator->shouldReceive('discoverFrameworkModules')
            ->once()
            ->andThrow($exception);

        $this->logger->shouldReceive('error')
            ->once()
            ->with('Framework Module discovery error', m::type('array'));

        $this->useCase->execute();
    });

    it('continues framework discovery even if Laravel discovery fails', function (): void {
        $this->orchestrator->shouldReceive('discoverLaravelModules')
            ->once()
            ->andThrow(new RuntimeException('fail'));
        $this->orchestrator->shouldReceive('discoverFrameworkModules')->once();

        $this->logger->shouldReceive('error')->once();

        $this->useCase->execute();
    });

    it('works without a logger', function (): void {
        $useCase = new DiscoverModulesUseCase($this->orchestrator);

        $this->orchestrator->shouldReceive('discoverLaravelModules')
            ->once()
            ->andThrow(new RuntimeException('fail'));
        $this->orchestrator->shouldReceive('discoverFrameworkModules')->once();

        $useCase->execute();
    });
});
