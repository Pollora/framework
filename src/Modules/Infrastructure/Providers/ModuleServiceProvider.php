<?php

declare(strict_types=1);

namespace Pollora\Modules\Infrastructure\Providers;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Pollora\Logging\Application\Services\LoggingService;
use Pollora\Logging\Domain\ValueObjects\LogContext;
use Pollora\Modules\Domain\Contracts\ModuleDiscoveryOrchestratorInterface;
use Pollora\Modules\Domain\Contracts\ModuleRepositoryInterface;
use Pollora\Modules\Infrastructure\Services\ModuleAutoloader;
use Pollora\Modules\Infrastructure\Services\ModuleBootstrap;
use Pollora\Modules\Infrastructure\Services\ModuleDiscoveryOrchestrator;
use Pollora\Modules\Infrastructure\Services\ModuleManifest;

/**
 * Main service provider for the generic module system.
 *
 * This provider follows the nwidart/laravel-modules pattern but adapted for our architecture.
 */
class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register ModuleAutoloader service
        $this->app->singleton(ModuleAutoloader::class);

        // Register ModuleDiscoveryOrchestrator
        $this->app->singleton(ModuleDiscoveryOrchestrator::class);

        // Register interface binding
        $this->app->bind(ModuleDiscoveryOrchestratorInterface::class, ModuleDiscoveryOrchestrator::class);

        // Register alias for easier access
        $this->app->alias(ModuleDiscoveryOrchestrator::class, 'modules.discovery');

        // Register new generic module services
        $this->app->singleton(\Pollora\Modules\Infrastructure\Services\ModuleConfigurationLoader::class);

        $this->app->singleton(\Pollora\Modules\Infrastructure\Services\ModuleComponentManager::class);

        $this->app->singleton(\Pollora\Modules\Infrastructure\Services\ModuleAssetManager::class);

        // Merge configuration
        $this->mergeConfigFrom(__DIR__.'/../../config/modules.php', 'modules');
    }

    public function boot(Router $router): void
    {
        // Load helper functions
        $this->loadHelperFunctions();

        // Legacy services kept for compatibility but simplified
        $this->app->singleton(ModuleManifest::class, fn ($app): \Pollora\Modules\Infrastructure\Services\ModuleManifest => new ModuleManifest(
            new Filesystem,
            $this->getModulePaths(),
            $this->getCachedModulePath(),
            $app->make(ModuleRepositoryInterface::class) // No longer using legacy scout
        ));

        $this->app->singleton(ModuleBootstrap::class, fn ($app): \Pollora\Modules\Infrastructure\Services\ModuleBootstrap => new ModuleBootstrap(
            $app,
            $app->make(ModuleRepositoryInterface::class),
            $router
        ));

        // Discover Laravel modules but don't apply yet
        $this->discoverLaravelModules();
        $this->applyLaravelModules();

        // Discover framework modules
        $this->discoverFrameworkModules();
        $this->applyFrameworkModules();

        // Setup Laravel Module discovery only
        $this->app->booted(function (): void {

            // Fire event to notify that modules are ready
            Event::dispatch('modules.routes.registered');
        });
    }

    /**
     * Load helper functions.
     */
    protected function loadHelperFunctions(): void
    {
        require_once __DIR__.'/../../UI/Helpers/discovery_functions.php';
    }

    /**
     * Get module paths from configuration.
     */
    protected function getModulePaths(): array
    {
        return [$this->app['config']->get('modules.paths.modules', base_path('modules'))];
    }

    /**
     * Get the cached module path.
     */
    protected function getCachedModulePath(): string
    {
        return Str::replaceLast('services.php', 'modules.php', $this->app->getCachedServicesPath());
    }

    /**
     * Discover Laravel modules using nwidart/laravel-modules (discovery only, no apply).
     */
    protected function discoverLaravelModules(): void
    {
        if (! $this->app->bound(ModuleDiscoveryOrchestratorInterface::class)) {
            return;
        }

        try {
            /** @var ModuleDiscoveryOrchestratorInterface $orchestrator */
            $orchestrator = $this->app->make(ModuleDiscoveryOrchestratorInterface::class);

            // Check if orchestrator has Laravel module discovery capability
            if (method_exists($orchestrator, 'discoverLaravelModules')) {
                $orchestrator->discoverLaravelModules();
            }
        } catch (\Throwable $e) {
            $loggingService = $this->app->make(LoggingService::class);
            $loggingService->error(
                'Laravel Module discovery error in ModuleServiceProvider: {message}',
                LogContext::fromException('Modules', $e)
            );
        }
    }

    /**
     * Apply discovered Laravel modules.
     */
    protected function applyLaravelModules(): void
    {
        if (! $this->app->bound(ModuleDiscoveryOrchestratorInterface::class)) {
            return;
        }

        try {
            /** @var ModuleDiscoveryOrchestratorInterface $orchestrator */
            $orchestrator = $this->app->make(ModuleDiscoveryOrchestratorInterface::class);

            // Check if orchestrator has Laravel module application capability
            if (method_exists($orchestrator, 'applyLaravelModules')) {
                $orchestrator->applyLaravelModules();
            }
        } catch (\Throwable $e) {
            $loggingService = $this->app->make(LoggingService::class);
            $loggingService->error(
                'Laravel Module apply error in ModuleServiceProvider: {message}',
                LogContext::fromException('Modules', $e)
            );
        }
    }

    /**
     * Discover framework modules from the src/ directory (discovery only, no apply).
     */
    protected function discoverFrameworkModules(): void
    {
        if (! $this->app->bound(ModuleDiscoveryOrchestratorInterface::class)) {
            return;
        }

        try {
            /** @var ModuleDiscoveryOrchestratorInterface $orchestrator */
            $orchestrator = $this->app->make(ModuleDiscoveryOrchestratorInterface::class);

            // Check if orchestrator has framework module discovery capability
            if (method_exists($orchestrator, 'discoverFrameworkModules')) {
                $orchestrator->discoverFrameworkModules();
            }
        } catch (\Throwable $e) {
            $loggingService = $this->app->make(LoggingService::class);
            $loggingService->error(
                'Framework Module discovery error in ModuleServiceProvider: {message}',
                LogContext::fromException('Modules', $e)
            );
        }
    }

    /**
     * Apply discovered framework modules.
     */
    protected function applyFrameworkModules(): void
    {
        if (! $this->app->bound(ModuleDiscoveryOrchestratorInterface::class)) {
            return;
        }

        try {
            /** @var ModuleDiscoveryOrchestratorInterface $orchestrator */
            $orchestrator = $this->app->make(ModuleDiscoveryOrchestratorInterface::class);

            // Check if orchestrator has framework module application capability
            if (method_exists($orchestrator, 'applyFrameworkModules')) {
                $orchestrator->applyFrameworkModules();
            }
        } catch (\Throwable $e) {
            $loggingService = $this->app->make(LoggingService::class);
            $loggingService->error(
                'Framework Module apply error in ModuleServiceProvider: {message}',
                LogContext::fromException('Modules', $e)
            );
        }
    }
}
