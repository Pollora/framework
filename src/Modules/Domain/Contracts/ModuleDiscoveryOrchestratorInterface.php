<?php

declare(strict_types=1);

namespace Pollora\Modules\Domain\Contracts;

/**
 * Interface for module discovery orchestrator services.
 *
 * This interface defines the contract for services that can orchestrate
 * the discovery of structures within specific paths (modules, themes, plugins, etc.).
 */
interface ModuleDiscoveryOrchestratorInterface extends ModuleDiscoveryInterface
{
    /**
     * Discover all enabled Laravel modules from nwidart/laravel-modules.
     */
    public function discoverLaravelModules(): void;

    /**
     * Apply all discovered Laravel modules.
     */
    public function applyLaravelModules(): void;

    /**
     * Get all enabled Laravel modules and their discovery data.
     *
     * @return array<string, array<string, mixed>>
     */
    public function discoverAndReturnLaravelModules(): array;

    /**
     * Discover all framework modules from the app/ directory.
     */
    public function discoverFrameworkModules(): void;

    /**
     * Apply all discovered framework modules.
     */
    public function applyFrameworkModules(): void;

    /**
     * Get all framework modules and their discovery data.
     *
     * @return array<string, array<string, mixed>>
     */
    public function discoverAndReturnFrameworkModules(): array;
}
