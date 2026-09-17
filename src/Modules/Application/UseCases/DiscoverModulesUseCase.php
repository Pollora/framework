<?php

declare(strict_types=1);

namespace Pollora\Modules\Application\UseCases;

use Pollora\Modules\Infrastructure\Services\ModuleDiscoveryOrchestrator;
use Psr\Log\LoggerInterface;

/**
 * Use case for discovering all module types (Laravel and Framework).
 *
 * Orchestrates the discovery phase for both nwidart/laravel-modules
 * and framework DDD modules from the app/ directory.
 */
class DiscoverModulesUseCase
{
    public function __construct(
        private readonly ModuleDiscoveryOrchestrator $orchestrator,
        private readonly ?LoggerInterface $logger = null
    ) {}

    /**
     * Execute the use case to discover all modules.
     */
    public function execute(): void
    {
        $this->discoverLaravelModules();
        $this->discoverFrameworkModules();
    }

    /**
     * Discover Laravel modules using nwidart/laravel-modules.
     */
    private function discoverLaravelModules(): void
    {
        try {
            $this->orchestrator->discoverLaravelModules();
        } catch (\Throwable $throwable) {
            $this->logger?->error('Laravel Module discovery error', [
                'exception' => $throwable,
            ]);
        }
    }

    /**
     * Discover framework modules from the app/ directory.
     */
    private function discoverFrameworkModules(): void
    {
        try {
            $this->orchestrator->discoverFrameworkModules();
        } catch (\Throwable $throwable) {
            $this->logger?->error('Framework Module discovery error', [
                'exception' => $throwable,
            ]);
        }
    }
}
