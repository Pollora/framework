<?php

declare(strict_types=1);

use Pollora\Modules\Domain\Contracts\ModuleDiscoveryOrchestratorInterface;
use Pollora\Modules\Infrastructure\Services\ModuleDiscoveryOrchestrator;

it('exposes discover(string $path): void on the orchestrator interface, which ThemeRegistrar and PluginRegistrar call', function (): void {
    $method = new ReflectionMethod(ModuleDiscoveryOrchestratorInterface::class, 'discover');

    expect($method->getParameters())->toHaveCount(1)
        ->and($method->getParameters()[0]->getName())->toBe('path')
        ->and((string) $method->getParameters()[0]->getType())->toBe('string')
        ->and((string) $method->getReturnType())->toBe('void')
        ->and(is_subclass_of(ModuleDiscoveryOrchestrator::class, ModuleDiscoveryOrchestratorInterface::class))->toBeTrue();
});
