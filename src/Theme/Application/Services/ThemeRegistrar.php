<?php

declare(strict_types=1);

namespace Pollora\Theme\Application\Services;

use Pollora\Logging\Application\Services\LoggingService;
use Pollora\Logging\Domain\ValueObjects\LogContext;
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
        protected WordPressThemeParser $themeParser,
        protected LoggingService $loggingService
    ) {}

    /**
     * Register a theme as the active theme.
     */
    public function register(): ThemeModuleInterface
    {
        $themeName = get_stylesheet();
        $themePath = get_stylesheet_directory();

        // Parse theme headers if not provided
        $styleCssPath = rtrim((string) $themePath, '/').'/style.css';
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
            $context = LogContext::fromClass(self::class, 'invalidateRepositoryCache');
            $this->loggingService->error('Failed to invalidate theme repository cache', $context, $e);
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

            // Get the app directory path for the theme
            $appPath = $this->getThemeAppPath($theme);
            if ($appPath && is_dir($appPath)) {
                $discoveryService->discover($appPath);
            }
        } catch (\Exception $e) {
            $context = new LogContext(
                module: 'Theme',
                class: self::class,
                method: 'discoverThemeStructures',
                extra: ['theme_name' => $theme->getName()]
            );
            $this->loggingService->error('Theme discovery error', $context, $e);
        }
    }

    /**
     * Get the app directory path for a theme
     */
    private function getThemeAppPath(ThemeModuleInterface $theme): ?string
    {
        $basePath = $theme->getPath();

        // Check for app/ directory first (preferred)
        $appPath = $basePath.'/app';
        if (is_dir($appPath)) {
            return $appPath;
        }

        // Fallback to src/ directory
        $srcPath = $basePath.'/src';
        if (is_dir($srcPath)) {
            return $srcPath;
        }

        return null;
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

        $context = new LogContext(
            module: 'Theme',
            class: self::class,
            method: 'processDiscoveredStructure',
            extra: [
                'scout_type' => $scoutType,
                'theme_name' => $theme->getName(),
                'structure_info' => $structureInfo,
            ]
        );
        $this->loggingService->debug("Discovered {$scoutType} in theme", $context);
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
                'theme'
            );
        } catch (\Exception $e) {
            $context = LogContext::fromClass(self::class, 'loadThemeConfiguration');
            $this->loggingService->error('Failed to load theme configuration', $context, $e);
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
            $context = LogContext::fromClass(self::class, 'setupThemeComponents');
            $this->loggingService->error('Failed to setup theme components', $context, $e);
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
            $context = LogContext::fromClass(self::class, 'setupThemeAssets');
            $this->loggingService->error('Failed to setup theme assets', $context, $e);
        }
    }
}
