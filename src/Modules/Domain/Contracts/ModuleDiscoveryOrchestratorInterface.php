<?php

declare(strict_types=1);

namespace Pollora\Modules\Domain\Contracts;

/**
 * Interface for module discovery orchestrator services.
 *
 * This interface defines the contract for services that can orchestrate
 * the discovery of structures within specific paths (modules, themes, plugins, etc.).
 */
interface ModuleDiscoveryOrchestratorInterface
{
    /**
     * Discover and apply all structures in a given path.
     *
     * @param  string  $path  The path to explore
     */
    public function discover(string $path): void;

    /**
     * Discover all structure types in a given path and return results.
     *
     * @param  string  $path  The path to explore
     * @return array<string, array> Results grouped by discovery type
     */
    public function discoverAndReturn(string $path): array;
}
