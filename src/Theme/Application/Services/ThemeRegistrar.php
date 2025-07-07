<?php

declare(strict_types=1);

namespace Pollora\Theme\Application\Services;

use Pollora\Modules\Domain\Contracts\ModuleDiscoveryOrchestratorInterface;
use Pollora\Modules\Domain\Contracts\ModuleRepositoryInterface;
use Pollora\Modules\Infrastructure\Services\ModuleAssetManager;
use Pollora\Modules\Infrastructure\Services\ModuleComponentManager;
use Pollora\Modules\Infrastructure\Services\ModuleConfigurationLoader;
use Pollora\Theme\Domain\Contracts\ThemeModuleInterface;
use Pollora\Theme\Domain\Contracts\ThemeRegistrarInterface;
use Pollora\Theme\Domain\Models\LaravelThemeModule;
use Pollora\Theme\Infrastructure\Repositories\ThemeRepository;
use Pollora\Theme\Infrastructure\Services\WordPressThemeParser;
use Psr\Container\ContainerInterface;

/**
 * Simplified theme self-registration service.
 */
class ThemeRegistrar implements ThemeRegistrarInterface
{
    private ?ThemeModuleInterface $activeTheme = null;

    public function __construct(
        protected ContainerInterface $app,
        protected WordPressThemeParser $themeParser
    ) {}

    /**
     * Register a theme as the active theme.
     */
    public function register(): ThemeModuleInterface
    {
        $themeName = get_stylesheet();
        $themePath = get_stylesheet_directory();

        // Parse theme headers if not provided
        $styleCssPath = rtrim($themePath, '/').'/style.css';
        $themeData = $this->themeParser->parseThemeHeaders($styleCssPath);

        // Create the theme module
        $theme = $this->createThemeModule($themeName, $themePath);
        $theme->registerAutoloading();
        $theme->setHeaders($themeData);
        $theme->setEnabled(true);

        // Register as the active theme
        $this->activeTheme = $theme;

        // Invalidate repository cache and discover structures
        $this->invalidateRepositoryCache();
        $this->discoverThemeStructures($theme);

        // Load theme configuration
        $this->loadThemeConfiguration($theme);

        // Setup theme components
        $this->setupThemeComponents($theme);

        // Setup theme assets and includes
        $this->setupThemeAssets($theme);

        // Register and boot the theme
        $theme->register();
        $theme->boot();

        return $theme;
    }

    /**
     * Create theme module instance.
     */
    protected function createThemeModule(string $themeName, string $themePath): LaravelThemeModule
    {
        if ($this->app->has('app') && method_exists($this->app->get('app'), 'make')) {
            return new LaravelThemeModule($themeName, $themePath, $this->app->get('app'));
        }

        return new LaravelThemeModule($themeName, $themePath, $this->app);
    }

    /**
     * Invalidate the theme repository cache.
     */
    protected function invalidateRepositoryCache(): void
    {
        if (! $this->app->has(ModuleRepositoryInterface::class)) {
            return;
        }

        try {
            $repository = $this->app->get(ModuleRepositoryInterface::class);

            if ($repository instanceof ThemeRepository) {
                $repository->resetCache();
            }
        } catch (\Exception $e) {
            $this->logError('Failed to invalidate theme repository cache: '.$e->getMessage());
        }
    }

    /**
     * Perform on-demand discovery for theme structures.
     */
    protected function discoverThemeStructures(ThemeModuleInterface $theme): void
    {
        if (! $this->app->has(ModuleDiscoveryOrchestratorInterface::class)) {
            return;
        }

        try {
            $discoveryService = $this->app->get(ModuleDiscoveryOrchestratorInterface::class);

            $discoveryService->discover($theme->getPath());
        } catch (\Exception $e) {
            $this->logError("Theme discovery error for {$theme->getName()}: ".$e->getMessage());
        }
    }

    /**
     * Process a discovered structure for the theme.
     */
    protected function processDiscoveredStructure($structure, string $scoutType, ThemeModuleInterface $theme): void
    {
        if (! $this->isDebugMode()) {
            return;
        }

        $structureInfo = is_object($structure) && method_exists($structure, 'getFqn')
            ? $structure->getFqn()
            : 'unknown';

        $this->logError("Discovered {$scoutType} in theme {$theme->getName()}: {$structureInfo}");
    }

    /**
     * Get the currently registered active theme.
     */
    public function getActiveTheme(): ?ThemeModuleInterface
    {
        return $this->activeTheme;
    }

    /**
     * Check if a theme is registered as active.
     */
    public function isThemeActive(string $themeName): bool
    {
        return $this->activeTheme?->getLowerName() === strtolower($themeName);
    }

    /**
     * Reset the active theme registration.
     */
    public function resetActiveTheme(): void
    {
        $this->activeTheme = null;
        $this->invalidateRepositoryCache();
    }

    /**
     * Check if debug mode is enabled.
     */
    protected function isDebugMode(): bool
    {
        return defined('WP_DEBUG') && WP_DEBUG;
    }

    /**
     * Log error message.
     */
    protected function logError(string $message): void
    {
        if (function_exists('error_log')) {
            error_log($message);
        }
    }

    /**
     * Load theme-specific configuration.
     */
    protected function loadThemeConfiguration(ThemeModuleInterface $theme): void
    {
        if (! $this->app->has(ModuleConfigurationLoader::class)) {
            return;
        }

        try {
            /** @var ModuleConfigurationLoader $configLoader */
            $configLoader = $this->app->get(ModuleConfigurationLoader::class);

            $configLoader->loadModuleConfiguration(
                $theme->getPath(),
                'theme', // module type
                $theme->getLowerName()
            );
        } catch (\Exception $e) {
            $this->logError('Failed to load theme configuration: '.$e->getMessage());
        }
    }

    /**
     * Setup theme-specific components.
     */
    protected function setupThemeComponents(ThemeModuleInterface $theme): void
    {
        if (! $this->app->has(ModuleComponentManager::class)) {
            return;
        }

        try {
            /** @var ModuleComponentManager $componentManager */
            $componentManager = $this->app->get(ModuleComponentManager::class);

            // Define theme-specific components
            $themeComponents = [
                \Pollora\Theme\Domain\Models\ThemeInitializer::class,
                \Pollora\BlockPattern\UI\PatternComponent::class,
                \Pollora\Theme\Domain\Models\Menus::class,
                \Pollora\Theme\Infrastructure\Services\Support::class,
                \Pollora\Theme\Domain\Models\Sidebar::class,
                \Pollora\Theme\Domain\Models\Templates::class,
                \Pollora\Theme\Domain\Models\ImageSize::class,
            ];

            $moduleId = 'theme.'.$theme->getLowerName();

            $componentManager->registerModuleComponents($moduleId, $themeComponents);
            $componentManager->initializeModuleComponents($moduleId);
        } catch (\Exception $e) {
            $this->logError('Failed to setup theme components: '.$e->getMessage());
        }
    }

    /**
     * Setup theme assets and includes.
     */
    protected function setupThemeAssets(ThemeModuleInterface $theme): void
    {
        if (! $this->app->has(ModuleAssetManager::class)) {
            return;
        }

        try {
            /** @var ModuleAssetManager $assetManager */
            $assetManager = $this->app->get(ModuleAssetManager::class);

            // Setup asset management (theme always uses 'theme' as container name)
            $assetManager->setupModuleAssets(
                $theme->getLowerName(),
                $theme->getPath(),
                'theme'
            );

            // Load theme includes
            $assetManager->loadModuleIncludes($theme->getPath());

            // Register Blade directives
            $assetManager->registerModuleBladeDirectives($theme->getPath());
        } catch (\Exception $e) {
            $this->logError('Failed to setup theme assets: '.$e->getMessage());
        }
    }
}
