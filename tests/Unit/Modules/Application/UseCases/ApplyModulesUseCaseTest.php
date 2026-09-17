<?php

declare(strict_types=1);

use Mockery as m;
use Pollora\Modules\Application\UseCases\ApplyModulesUseCase;
use Pollora\Modules\Infrastructure\Services\ModuleDiscoveryOrchestrator;
use Psr\Log\LoggerInterface;

beforeEach(function (): void {
    $this->orchestrator = m::mock(ModuleDiscoveryOrchestrator::class);
    $this->logger = m::mock(LoggerInterface::class);
    $this->useCase = new ApplyModulesUseCase($this->orchestrator, $this->logger);
});

describe('ApplyModulesUseCase', function (): void {

    it('applies both Laravel and Framework modules', function (): void {
        $this->orchestrator->shouldReceive('applyLaravelModules')->once();
        $this->orchestrator->shouldReceive('applyFrameworkModules')->once();

        $this->useCase->execute();
    });

    it('handles Laravel module apply errors gracefully', function (): void {
        $exception = new RuntimeException('apply failed');

        $this->orchestrator->shouldReceive('applyLaravelModules')
            ->once()
            ->andThrow($exception);
        $this->orchestrator->shouldReceive('applyFrameworkModules')->once();

        $this->logger->shouldReceive('error')
            ->once()
            ->with('Laravel Module apply error', m::type('array'));

        $this->useCase->execute();
    });

    it('handles Framework module apply errors gracefully', function (): void {
        $exception = new RuntimeException('apply failed');

        $this->orchestrator->shouldReceive('applyLaravelModules')->once();
        $this->orchestrator->shouldReceive('applyFrameworkModules')
            ->once()
            ->andThrow($exception);

        $this->logger->shouldReceive('error')
            ->once()
            ->with('Framework Module apply error', m::type('array'));

        $this->useCase->execute();
    });

    it('continues framework apply even if Laravel apply fails', function (): void {
        $this->orchestrator->shouldReceive('applyLaravelModules')
            ->once()
            ->andThrow(new RuntimeException('fail'));
        $this->orchestrator->shouldReceive('applyFrameworkModules')->once();

        $this->logger->shouldReceive('error')->once();

        $this->useCase->execute();
    });

    it('works without a logger', function (): void {
        $useCase = new ApplyModulesUseCase($this->orchestrator);

        $this->orchestrator->shouldReceive('applyLaravelModules')
            ->once()
            ->andThrow(new RuntimeException('fail'));
        $this->orchestrator->shouldReceive('applyFrameworkModules')->once();

        $useCase->execute();
    });
});
