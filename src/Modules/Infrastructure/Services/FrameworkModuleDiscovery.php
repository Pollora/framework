<?php

declare(strict_types=1);

namespace Pollora\Modules\Infrastructure\Services;

use Illuminate\Container\Container;
use Pollora\Discovery\Application\Services\DiscoveryManager;
use Pollora\Discovery\Domain\Contracts\DiscoveryEngineInterface;
use Pollora\Discovery\Domain\Models\DirectoryLocation;
use Pollora\Modules\Domain\Contracts\ModuleDiscoveryOrchestratorInterface;

/**
 * Laravel Application Module Discovery Service
 *
 * This service handles discovery for Laravel modules within the application's app/ directory.
 * It scans each module in the app/ directory and runs discovery on their Application, Domain,
 * Infrastructure, and UI layers to find components marked with discovery attributes.
 *
 * Unlike LaravelModuleDiscovery which handles external nwidart/laravel-modules,
 * this service handles the Laravel application's own internal module structure.
 */
class FrameworkModuleDiscovery implements ModuleDiscoveryOrchestratorInterface
{
    /**
     * Array of discovered framework modules with their discovery engines
     *
     * @var array<string, array{path: string, engine: DiscoveryEngineInterface}>
     */
    protected array $discoveredModules = [];

    /**
     * Base path to the Laravel application's app directory
     */
    protected string $basePath;

    /**
     * Laravel Application Module Discovery constructor
     *
     * @param  Container  $container  Laravel container instance
     */
    public function __construct(
        protected Container $container
    ) {
        $this->basePath = app_path();
    }

    /**
     * Discover all Laravel application modules and store them for later application
     *
     * This method scans the app/ directory for modules and runs discovery
     * on each one without applying the results immediately.
     */
    public function discoverFrameworkModules(): void
    {
        if (! $this->container->bound(DiscoveryEngineInterface::class)) {
            return;
        }

        try {
            $this->discoverModuleOnly('app', $this->basePath);
        } catch (\Throwable $e) {
            if (function_exists('error_log')) {
                error_log('Framework Module discovery error: '.$e->getMessage());
            }
        }
    }

    /**
     * Apply all discovered framework modules
     *
     * This method applies the discovery results for all previously discovered modules.
     * It should be called after discoverFrameworkModules() to register the discovered components.
     */
    public function applyFrameworkModules(): void
    {
        if ($this->discoveredModules === []) {
            return;
        }

        try {
            foreach ($this->discoveredModules as $moduleName => $moduleData) {
                $moduleData['engine']->apply();
            }
        } catch (\Throwable $e) {
            if (function_exists('error_log')) {
                error_log('Framework Module apply error: '.$e->getMessage());
            }
        }
    }

    /**
     * Discover a specific framework module by name
     *
     * @param  string  $moduleName  Name of the module to discover
     */
    public function discoverFrameworkModule(string $moduleName): void
    {
        if (! $this->container->bound(DiscoveryEngineInterface::class)) {
            return;
        }

        try {
            $modules = $this->getFrameworkModules();

            if (! isset($modules[$moduleName])) {
                return;
            }

            $this->discoverModule($moduleName, $modules[$moduleName]);
        } catch (\Throwable $e) {
            if (function_exists('error_log')) {
                error_log("Framework Module discovery error for {$moduleName}: ".$e->getMessage());
            }
        }
    }

    /**
     * Get all framework modules and their discovery data
     *
     * @return array<string, array<string, mixed>> Array of module names and their discovery results
     */
    public function discoverAndReturnFrameworkModules(): array
    {
        if (! $this->container->bound(DiscoveryManager::class)) {
            return [];
        }

        $results = [];

        try {
            $modules = $this->getFrameworkModules();

            foreach ($modules as $moduleName => $modulePath) {
                $moduleResults = $this->discoverAndReturnModule($moduleName, $modulePath);
                if ($moduleResults !== []) {
                    $results[$moduleName] = $moduleResults;
                }
            }
        } catch (\Throwable $e) {
            if (function_exists('error_log')) {
                error_log('Framework Module discovery error: '.$e->getMessage());
            }
        }

        return $results;
    }

    /**
     * {@inheritDoc}
     *
     * This method is required by the interface but not used for framework modules.
     * Framework modules are discovered by their module structure, not by path.
     */
    public function discover(string $path): void
    {
        // This method is required by the interface but not used for framework modules
        // Framework modules are discovered by their module structure, not by path
    }

    /**
     * {@inheritDoc}
     *
     * This method is required by the interface but not used for framework modules.
     * Framework modules are discovered by their module structure, not by path.
     */
    public function discoverAndReturn(string $path): array
    {
        // This method is required by the interface but not used for framework modules
        // Framework modules are discovered by their module structure, not by path
        return [];
    }

    /**
     * Get all Laravel application modules from the app/ directory
     *
     * @return array<string, string> Array of module names and their full paths
     */
    protected function getFrameworkModules(): array
    {
        $modules = [];

        if (! is_dir($this->basePath)) {
            return $modules;
        }

        try {
            $directories = new \DirectoryIterator($this->basePath);

            foreach ($directories as $directory) {
                if ($directory->isDot() || ! $directory->isDir()) {
                    continue;
                }

                $moduleName = $directory->getBasename();

                // Only include modules that have typical DDD structure
                if ($this->hasValidModuleStructure($directory->getPathname())) {
                    $modules[$moduleName] = $directory->getPathname();
                }
            }
        } catch (\Throwable $e) {
            if (function_exists('error_log')) {
                error_log('Error scanning framework modules: '.$e->getMessage());
            }
        }

        return $modules;
    }

    /**
     * Check if a directory has a valid module structure
     *
     * A valid module structure should have at least one of:
     * - Application/ directory
     * - Domain/ directory
     * - Infrastructure/ directory
     * - UI/ directory
     *
     * @param  string  $path  Path to the potential module directory
     * @return bool True if the directory has a valid module structure
     */
    protected function hasValidModuleStructure(string $path): bool
    {
        $requiredDirectories = ['Application', 'Domain', 'Infrastructure', 'UI'];

        foreach ($requiredDirectories as $directory) {
            if (is_dir($path.DIRECTORY_SEPARATOR.$directory)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Run discovery on a specific module without applying
     *
     * @param  string  $moduleName  Name of the module
     * @param  string  $modulePath  Full path to the module directory
     */
    protected function discoverModuleOnly(string $moduleName, string $modulePath): void
    {
        if (! $this->container->bound(DiscoveryEngineInterface::class)) {
            return;
        }

        try {
            // Get a fresh engine instance from container (with all discoveries registered)
            $engine = $this->container->make(DiscoveryEngineInterface::class);

            // Clear any existing locations to avoid accumulation

            // Add discovery locations for each DDD layer
            $this->addModuleDiscoveryLocations($engine, $moduleName, $modulePath);

            // Run discovery
            $engine->discover();

            // Store engine for later application
            $this->discoveredModules[$moduleName] = [
                'path' => $modulePath,
                'engine' => $engine,
            ];
        } catch (\Throwable $e) {
            if (function_exists('error_log')) {
                error_log("Discovery error for framework module {$moduleName}: ".$e->getMessage());
            }
        }
    }

    /**
     * Run discovery on a specific module and return results
     *
     * @param  string  $moduleName  Name of the module
     * @param  string  $modulePath  Full path to the module directory
     * @return array<string, mixed> Discovery results for the module
     */
    protected function discoverAndReturnModule(string $moduleName, string $modulePath): array
    {
        if (! $this->container->bound(DiscoveryManager::class)) {
            return [];
        }

        try {
            /** @var DiscoveryManager $manager */
            $manager = $this->container->make(DiscoveryManager::class);

            $results = [];

            // Discover in each DDD layer
            $layers = ['Application', 'Domain', 'Infrastructure', 'UI'];

            foreach ($layers as $layer) {
                $layerPath = $modulePath.DIRECTORY_SEPARATOR.$layer;

                if (is_dir($layerPath)) {
                    $location = new DirectoryLocation($layerPath);
                    $layerResults = $manager->discoverAllInLocation($location);

                    if ($layerResults !== []) {
                        $results[$layer] = $layerResults;
                    }
                }
            }

            return $results;
        } catch (\Throwable $e) {
            if (function_exists('error_log')) {
                error_log("Discovery error for framework module {$moduleName}: ".$e->getMessage());
            }

            return [];
        }
    }

    /**
     * Add discovery locations for a module's DDD layers
     *
     * @param  DiscoveryEngineInterface  $engine  Discovery engine instance
     * @param  string  $moduleName  Name of the module
     * @param  string  $modulePath  Full path to the module directory
     */
    protected function addModuleDiscoveryLocations(DiscoveryEngineInterface $engine, string $moduleName, string $modulePath): void
    {
        $location = new DirectoryLocation($modulePath, 'App');
        $engine->addLocation($location);
    }

    /**
     * Get the list of discovered modules
     *
     * @return array<string, array{path: string, engine: DiscoveryEngineInterface}>
     */
    public function getDiscoveredModules(): array
    {
        return $this->discoveredModules;
    }

    /**
     * Clear all discovered modules
     */
    public function clearDiscoveredModules(): void
    {
        $this->discoveredModules = [];
    }

    /**
     * Check if a specific module has been discovered
     *
     * @param  string  $moduleName  Name of the module to check
     * @return bool True if the module has been discovered
     */
    public function hasDiscoveredModule(string $moduleName): bool
    {
        return isset($this->discoveredModules[$moduleName]);
    }

    /**
     * Get discovery engine for a specific module
     *
     * @param  string  $moduleName  Name of the module
     * @return DiscoveryEngineInterface|null Discovery engine instance or null if not found
     */
    public function getModuleDiscoveryEngine(string $moduleName): ?DiscoveryEngineInterface
    {
        return $this->discoveredModules[$moduleName]['engine'] ?? null;
    }
}
