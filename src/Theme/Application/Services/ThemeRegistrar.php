<?php

declare(strict_types=1);

namespace Pollora\Theme\Application\Services;

use Pollora\Modules\Domain\Contracts\ModuleRepositoryInterface;
use Pollora\Modules\Domain\Contracts\OnDemandDiscoveryInterface;
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
        $styleCssPath = rtrim($themePath, '/') . '/style.css';
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
        if (!$this->app->has(ModuleRepositoryInterface::class)) {
            return;
        }

        try {
            $repository = $this->app->get(ModuleRepositoryInterface::class);

            if ($repository instanceof ThemeRepository) {
                $repository->resetCache();
            }
        } catch (\Exception $e) {
            $this->logError('Failed to invalidate theme repository cache: ' . $e->getMessage());
        }
    }

    /**
     * Perform on-demand discovery for theme structures.
     */
    protected function discoverThemeStructures(ThemeModuleInterface $theme): void
    {
        if (!$this->app->has(OnDemandDiscoveryInterface::class)) {
            return;
        }

        try {
            $discoveryService = $this->app->get(OnDemandDiscoveryInterface::class);

            $discoveryService->discoverModule($theme->getPath());
        } catch (\Exception $e) {
            $this->logError("Theme discovery error for {$theme->getName()}: " . $e->getMessage());
        }
    }

    /**
     * Process a discovered structure for the theme.
     */
    protected function processDiscoveredStructure($structure, string $scoutType, ThemeModuleInterface $theme): void
    {
        if (!$this->isDebugMode()) {
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
}
