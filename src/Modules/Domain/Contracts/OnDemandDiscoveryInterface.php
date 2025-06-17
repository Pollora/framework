<?php

declare(strict_types=1);

namespace Pollora\Modules\Domain\Contracts;

use Spatie\StructureDiscoverer\Data\DiscoveredStructure;

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
     * @param string $path The path to explore
     * @param class-string $scoutClass The scout class to use
     * @return void
     */
    public function discoverInPath(string $path, string $scoutClass): void;

    /**
     * Discover all structure types in a given path.
     *
     * @param string $path The path to explore
     * @return void
     */
    public function discoverAllInPath(string $path): void;

    /**
     * Discover and process structures for a module (generic).
     *
     * @param string $modulePath The module directory path
     * @param callable|null $processor Optional processor function for each discovered structure
     * @return void
     */
    public function discoverModule(string $modulePath, ?callable $processor = null): void;

    /**
     * Discover and process structures for a theme.
     *
     * @param string $themePath The theme directory path
     * @param callable|null $processor Optional processor function for each discovered structure
     * @return void
     */
    public function discoverTheme(string $themePath, ?callable $processor = null): void;

    /**
     * Discover and process structures for a plugin.
     *
     * @param string $pluginPath The plugin directory path
     * @param callable|null $processor Optional processor function for each discovered structure
     * @return void
     */
    public function discoverPlugin(string $pluginPath, ?callable $processor = null): void;

    /**
     * Clear discovery cache.
     */
    public function clearCache(): void;

    /**
     * Clear cache for a specific path.
     *
     * @param string $path The path to clear cache for
     */
    public function clearCacheForPath(string $path): void;
}
