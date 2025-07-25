<?php

declare(strict_types=1);

namespace Pollora\Modules\Infrastructure\Services;

use Illuminate\Container\Container;
use Pollora\Discovery\Application\Services\DiscoveryManager;
use Pollora\Discovery\Domain\Contracts\DiscoveryEngineInterface;
use Pollora\Discovery\Domain\Models\DirectoryLocation;
use Pollora\Modules\Domain\Contracts\ModuleDiscoveryOrchestratorInterface;

/**
 * Module Discovery Orchestrator Service
 *
 * This service orchestrates the discovery of structures in modules, themes and plugins.
 * It provides a simplified interface over the Discovery system, allowing discovery
 * to be triggered at the moment a module, theme or plugin is registered.
 */
class ModuleDiscoveryOrchestrator implements ModuleDiscoveryOrchestratorInterface
{
    protected ?LaravelModuleDiscovery $laravelModuleDiscovery = null;

    protected ?FrameworkModuleDiscovery $frameworkModuleDiscovery = null;

    public function __construct(
        protected Container $container
    ) {}

    /**
     * {@inheritDoc}
     */
    public function discover(string $path): void
    {
        if (! is_dir($path) || ! $this->container->bound(DiscoveryEngineInterface::class)) {
            return;
        }

        try {
            /** @var DiscoveryEngineInterface $engine */
            $engine = $this->container->make(DiscoveryEngineInterface::class);

            $location = new DirectoryLocation($path);
            $engine->addLocation($location)->discover()->apply();
        } catch (\Throwable $e) {
            if (function_exists('error_log')) {
                error_log("Discovery error for path {$path}: ".$e->getMessage());
            }
        }
    }

    /**
     * {@inheritDoc}
     */
    public function discoverAndReturn(string $path): array
    {
        if (! is_dir($path) || ! $this->container->bound(DiscoveryManager::class)) {
            return [];
        }

        try {
            /** @var DiscoveryManager $manager */
            $manager = $this->container->make(DiscoveryManager::class);

            $location = new DirectoryLocation($path);

            return $manager->discoverAllInLocation($location);
        } catch (\Throwable $e) {
            if (function_exists('error_log')) {
                error_log("Discovery error for path {$path}: ".$e->getMessage());
            }

            return [];
        }
    }

    /**
     * Discover all enabled Laravel modules from nwidart/laravel-modules.
     */
    public function discoverLaravelModules(): void
    {
        $this->getLaravelModuleDiscovery()->discoverLaravelModules();
    }

    /**
     * Apply all discovered Laravel modules.
     */
    public function applyLaravelModules(): void
    {
        $this->getLaravelModuleDiscovery()->applyLaravelModules();
    }

    /**
     * Discover a specific Laravel module by name.
     */
    public function discoverLaravelModule(string $moduleName): void
    {
        $this->getLaravelModuleDiscovery()->discoverLaravelModule($moduleName);
    }

    /**
     * Get all enabled Laravel modules and their discovery data.
     */
    public function discoverAndReturnLaravelModules(): array
    {
        return $this->getLaravelModuleDiscovery()->discoverAndReturnLaravelModules();
    }

    /**
     * Discover all framework modules from the src/ directory.
     */
    public function discoverFrameworkModules(): void
    {
        $this->getFrameworkModuleDiscovery()->discoverFrameworkModules();
    }

    /**
     * Apply all discovered framework modules.
     */
    public function applyFrameworkModules(): void
    {
        $this->getFrameworkModuleDiscovery()->applyFrameworkModules();
    }

    /**
     * Discover a specific framework module by name.
     *
     * @param  string  $moduleName  Name of the framework module to discover
     */
    public function discoverFrameworkModule(string $moduleName): void
    {
        $this->getFrameworkModuleDiscovery()->discoverFrameworkModule($moduleName);
    }

    /**
     * Get all framework modules and their discovery data.
     *
     * @return array<string, array<string, mixed>> Array of module names and their discovery results
     */
    public function discoverAndReturnFrameworkModules(): array
    {
        return $this->getFrameworkModuleDiscovery()->discoverAndReturnFrameworkModules();
    }

    /**
     * Get or create the Laravel module discovery service.
     */
    protected function getLaravelModuleDiscovery(): LaravelModuleDiscovery
    {
        if (! $this->laravelModuleDiscovery instanceof \Pollora\Modules\Infrastructure\Services\LaravelModuleDiscovery) {
            $this->laravelModuleDiscovery = new LaravelModuleDiscovery($this->container);
        }

        return $this->laravelModuleDiscovery;
    }

    /**
     * Get or create the framework module discovery service.
     */
    protected function getFrameworkModuleDiscovery(): FrameworkModuleDiscovery
    {
        if (! $this->frameworkModuleDiscovery instanceof \Pollora\Modules\Infrastructure\Services\FrameworkModuleDiscovery) {
            $this->frameworkModuleDiscovery = new FrameworkModuleDiscovery($this->container);
        }

        return $this->frameworkModuleDiscovery;
    }
}
