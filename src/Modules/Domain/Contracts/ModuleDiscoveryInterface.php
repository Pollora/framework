<?php

declare(strict_types=1);

namespace Pollora\Modules\Domain\Contracts;

/**
 * Base interface for module discovery services.
 *
 * Provides the core discovery contract for path-based module discovery.
 * Specialized discovery services (Laravel, Framework) implement this directly,
 * while the orchestrator extends it with higher-level coordination methods.
 */
interface ModuleDiscoveryInterface
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
     * @return array<string, array<string, mixed>> Results grouped by discovery type
     */
    public function discoverAndReturn(string $path): array;
}
