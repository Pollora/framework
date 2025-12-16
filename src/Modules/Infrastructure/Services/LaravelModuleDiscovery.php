<?php

declare(strict_types=1);

namespace Pollora\Modules\Infrastructure\Services;

use Illuminate\Container\Container;
use Nwidart\Modules\Contracts\RepositoryInterface;
use Pollora\Discovery\Application\Services\DiscoveryManager;
use Pollora\Discovery\Domain\Contracts\DiscoveryEngineInterface;
use Pollora\Discovery\Domain\Models\DirectoryLocation;
use Pollora\Logging\Application\Services\LoggingService;
use Pollora\Logging\Domain\ValueObjects\LogContext;
use Pollora\Modules\Domain\Contracts\ModuleDiscoveryOrchestratorInterface;

/**
 * Laravel Module Discovery Service
 *
 * This service handles discovery for Laravel Modules created with nwidart/laravel-modules.
 * It integrates with the Module facade to find enabled modules and run discovery on them.
 */
class LaravelModuleDiscovery implements ModuleDiscoveryOrchestratorInterface
{
    protected array $discoveredModules = [];

    public function __construct(
        protected Container $container,
        protected LoggingService $loggingService
    ) {}

    /**
     * Discover all enabled Laravel modules and store them for later application.
     */
    public function discoverLaravelModules(): void
    {
        if (! $this->isLaravelModulesAvailable()) {
            return;
        }

        try {
            $enabledModules = $this->getEnabledModules();

            foreach ($enabledModules as $module) {
                $this->discoverModuleOnly($module);
            }
        } catch (\Throwable $e) {
            $this->loggingService->error(
                'Laravel Module discovery error: {message}',
                LogContext::fromException('Modules', $e)
            );
        }
    }

    /**
     * Apply all discovered Laravel modules.
     */
    public function applyLaravelModules(): void
    {
        if ($this->discoveredModules === []) {
            return;
        }

        try {
            foreach ($this->discoveredModules as $engineData) {
                $engineData['engine']->apply();
            }
        } catch (\Throwable $e) {
            $this->loggingService->error(
                'Laravel Module apply error: {message}',
                LogContext::fromException('Modules', $e)
            );
        }
    }

    /**
     * Discover a specific Laravel module by name.
     */
    public function discoverLaravelModule(string $moduleName): void
    {
        if (! $this->isLaravelModulesAvailable()) {
            return;
        }

        try {
            $module = $this->findModule($moduleName);
            if ($module && $module->isEnabled()) {
                $this->discoverModule($module);
            }
        } catch (\Throwable $e) {
            $this->loggingService->error(
                'Laravel Module discovery error for {moduleName}: {message}',
                LogContext::fromException('Modules', $e)->merge([
                    'moduleName' => $moduleName,
                ])
            );
        }
    }

    /**
     * Get all enabled Laravel modules and their discovery data.
     */
    public function discoverAndReturnLaravelModules(): array
    {
        if (! $this->isLaravelModulesAvailable()) {
            return [];
        }

        $results = [];

        try {
            $enabledModules = $this->getEnabledModules();

            foreach ($enabledModules as $module) {
                $moduleResults = $this->discoverAndReturnModule($module);
                if ($moduleResults !== []) {
                    $results[$module->getName()] = $moduleResults;
                }
            }
        } catch (\Throwable $e) {
            $this->loggingService->error(
                'Laravel Module discovery error: {message}',
                LogContext::fromException('Modules', $e)
            );
        }

        return $results;
    }

    /**
     * {@inheritDoc}
     */
    public function discover(string $path): void
    {
        // This method is required by the interface but not used for Laravel modules
        // Laravel modules are discovered by their module structure, not by path
    }

    /**
     * {@inheritDoc}
     */
    public function discoverAndReturn(string $path): array
    {
        // This method is required by the interface but not used for Laravel modules
        // Laravel modules are discovered by their module structure, not by path
        return [];
    }

    /**
     * Check if Laravel Modules package is available.
     */
    protected function isLaravelModulesAvailable(): bool
    {
        return interface_exists(\Nwidart\Modules\Contracts\RepositoryInterface::class) &&
               $this->container->bound('modules');
    }

    /**
     * Get all enabled Laravel modules.
     */
    protected function getEnabledModules(): array
    {
        try {
            /** @var RepositoryInterface $repository */
            $repository = $this->container->make('modules');

            return $repository->allEnabled();
        } catch (\Throwable $e) {
            $this->loggingService->error(
                'Error getting enabled modules: {message}',
                LogContext::fromException('Modules', $e)
            );

            return [];
        }
    }

    /**
     * Find a specific Laravel module by name.
     */
    protected function findModule(string $name): ?\Nwidart\Modules\Module
    {
        try {
            /** @var RepositoryInterface $repository */
            $repository = $this->container->make('modules');

            return $repository->find($name);
        } catch (\Throwable $e) {
            $this->loggingService->error(
                'Error finding module {moduleName}: {message}',
                LogContext::fromException('Modules', $e)->merge([
                    'moduleName' => $name,
                ])
            );

            return null;
        }
    }

    /**
     * Run discovery on a specific module without applying.
     */
    protected function discoverModuleOnly($module): void
    {
        $appPath = $module->getPath().'/app';

        if (! is_dir($appPath) || ! $this->container->bound(DiscoveryEngineInterface::class)) {
            return;
        }
        try {
            // Create a fresh engine instance for each module to avoid interference
            $engine = $this->container->makeWith(DiscoveryEngineInterface::class, []);

            $location = new DirectoryLocation($appPath);
            $engine->addLocation($location)->discover();

            // Store engine for later application
            $this->discoveredModules[$module->getName()] = [
                'module' => $module,
                'engine' => $engine,
                'path' => $appPath,
            ];
        } catch (\Throwable $e) {
            $this->loggingService->error(
                'Discovery error for module {moduleName}: {message}',
                LogContext::fromException('Modules', $e)->merge([
                    'moduleName' => $module->getName(),
                ])
            );
        }
    }

    /**
     * Run discovery on a specific module (legacy method for compatibility).
     */
    protected function discoverModule($module): void
    {
        $this->discoverModuleOnly($module);

        // Apply immediately for backward compatibility
        if (isset($this->discoveredModules[$module->getName()])) {
            $this->discoveredModules[$module->getName()]['engine']->apply();
        }
    }

    /**
     * Run discovery on a specific module and return results.
     */
    protected function discoverAndReturnModule($module): array
    {
        $appPath = $module->getPath().'/app';

        if (! is_dir($appPath) || ! $this->container->bound(DiscoveryManager::class)) {
            return [];
        }

        try {
            /** @var DiscoveryManager $manager */
            $manager = $this->container->make(DiscoveryManager::class);

            $location = new DirectoryLocation($appPath);

            return $manager->discoverAllInLocation($location);
        } catch (\Throwable $e) {
            $this->loggingService->error(
                'Discovery error for module {moduleName}: {message}',
                LogContext::fromException('Modules', $e)->merge([
                    'moduleName' => $module->getName(),
                ])
            );

            return [];
        }
    }
}
