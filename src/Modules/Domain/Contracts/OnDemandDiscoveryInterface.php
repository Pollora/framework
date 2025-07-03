<?php

declare(strict_types=1);

namespace Pollora\Modules\Domain\Contracts;

/**
 * Interface for on-demand discovery services.
 *
 * This interface defines the contract for services that can discover
 * structures within specific paths (modules, themes, plugins, etc.) on demand.
 * It supports both legacy scout-based discovery and the new Discovery system.
 */
interface OnDemandDiscoveryInterface
{
    /**
     * Discover structures in a specific path using a given scout class.
     *
     * @param  string  $path  The path to explore
     * @param  class-string  $scoutClass  The scout class to use (legacy support)
     */
    public function discoverInPath(string $path, string $scoutClass): void;

    /**
     * Discover and process structures for a module (generic).
     *
     * @param  string  $path  The module directory path
     */
    public function discoverModule(string $path): void;

    /**
     * Discover structures in a theme directory.
     *
     * @param  string  $themePath  The theme directory path
     */
    public function discoverTheme(string $themePath): void;

    /**
     * Discover structures in a plugin directory.
     *
     * @param  string  $pluginPath  The plugin directory path
     */
    public function discoverPlugin(string $pluginPath): void;

    /**
     * Discover all structure types in a given path.
     *
     * @param  string  $path  The path to explore
     * @return array<string, array> Results grouped by discovery type
     */
    public function discoverAllInPath(string $path): array;
}
