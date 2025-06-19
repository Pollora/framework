<?php

declare(strict_types=1);

namespace Pollora\Modules\Domain\Contracts;

/**
 * Interface for on-demand discovery services.
 *
 * This interface defines the contract for services that can discover
 * structures within specific paths (modules, themes, plugins, etc.) on demand.
 */
interface OnDemandDiscoveryInterface
{
    /**
     * Discover structures in a specific path using a given scout class.
     *
     * @param  string  $path  The path to explore
     * @param  class-string  $scoutClass  The scout class to use
     */
    public function discoverInPath(string $path, string $scoutClass): void;

    /**
     * Discover and process structures for a module (generic).
     *
     * @param  string  $path  The module directory path
     */
    public function discoverModule(string $path): void;
}
