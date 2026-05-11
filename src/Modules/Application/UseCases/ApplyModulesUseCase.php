<?php

declare(strict_types=1);

namespace Pollora\Modules\Application\UseCases;

use Pollora\Modules\Infrastructure\Services\ModuleDiscoveryOrchestrator;
use Psr\Log\LoggerInterface;

/**
 * Use case for applying all discovered modules (Laravel and Framework).
 *
 * Executes the application phase for both nwidart/laravel-modules
 * and framework DDD modules, registering their service providers,
 * routes, and other resources.
 */
class ApplyModulesUseCase
{
    public function __construct(
        private readonly ModuleDiscoveryOrchestrator $orchestrator,
        private readonly ?LoggerInterface $logger = null
    ) {}

    /**
     * Execute the use case to apply all discovered modules.
     */
    public function execute(): void
    {
        $this->applyLaravelModules();
        $this->applyFrameworkModules();
    }

    /**
     * Apply discovered Laravel modules.
     */
    private function applyLaravelModules(): void
    {
        try {
            $this->orchestrator->applyLaravelModules();
        } catch (\Throwable $throwable) {
            $this->logger?->error('Laravel Module apply error', [
                'exception' => $throwable,
            ]);
        }
    }

    /**
     * Apply discovered framework modules.
     */
    private function applyFrameworkModules(): void
    {
        try {
            $this->orchestrator->applyFrameworkModules();
        } catch (\Throwable $throwable) {
            $this->logger?->error('Framework Module apply error', [
                'exception' => $throwable,
            ]);
        }
    }
}
