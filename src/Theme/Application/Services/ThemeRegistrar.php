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
 * Application service for theme self-registration.
 *
 * This service allows themes to register themselves as active,
 * providing a more explicit and database-free approach to theme management.
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
     *
     * This method is called from the theme's functions.php file to declare
     * itself as the active theme, eliminating the need for database queries.
     */
    public function registerActiveTheme(string $themeName, string $themePath, array $themeData = []): ThemeModuleInterface
    {
        // Parse theme headers if not provided
        if (empty($themeData)) {
            $styleCssPath = rtrim($themePath, '/') . '/style.css';
            $themeData = $this->themeParser->parseThemeHeaders($styleCssPath);
        }

        // Create the theme module
        if ($this->app->has('app') && method_exists($this->app->get('app'), 'make')) {
            $theme = new LaravelThemeModule($themeName, $themePath, $this->app->get('app'));
        } else {
            $theme = new LaravelThemeModule($themeName, $themePath, $this->app);
        }

        $theme->registerAutoloading();

        // Set theme headers and mark as enabled/active
        $theme->setHeaders($themeData);
        $theme->setEnabled(true);

        // Register as the active theme
        $this->activeTheme = $theme;

        // Invalidate theme repository cache to ensure synchronization
        $this->invalidateRepositoryCache();

        // Perform on-demand discovery for the theme
        $this->discoverThemeStructures($theme);

        // Register and boot the theme
        $theme->register();
        $theme->boot();

        return $theme;
    }

    /**
     * Invalidate the theme repository cache when a new theme is registered.
     *
     * This ensures that the repository will reload themes and include
     * the newly registered active theme in its collections.
     */
    protected function invalidateRepositoryCache(): void
    {
        if ($this->app->has(ModuleRepositoryInterface::class)) {
            try {
                $repository = $this->app->get(ModuleRepositoryInterface::class);
                
                // Only reset cache if it's a ThemeRepository instance
                if ($repository instanceof ThemeRepository) {
                    $repository->resetCache();
                }
            } catch (\Exception $e) {
                // Log error but don't break theme registration
                if (function_exists('error_log')) {
                    error_log('Failed to invalidate theme repository cache: ' . $e->getMessage());
                }
            }
        }
    }

    /**
     * Perform on-demand discovery for theme structures.
     *
     * This method uses the OnDemandDiscoveryService to discover and process
     * all discoverable structures within the theme directory.
     */
    protected function discoverThemeStructures(ThemeModuleInterface $theme): void
    {
        // Only proceed if the discovery service is available
        if (!$this->app->has(OnDemandDiscoveryInterface::class)) {
            return;
        }

        try {
            $discoveryService = $this->app->get(OnDemandDiscoveryInterface::class);

            // Discover all structures in the theme
            $discoveryService->discoverTheme($theme->getPath(), function ($structure, $scoutType, $themePath) use ($theme) {
                $this->processDiscoveredStructure($structure, $scoutType, $theme);
            });
        } catch (\Exception $e) {
            // Log error but don't break theme registration
            if (function_exists('error_log')) {
                error_log("Theme discovery error for {$theme->getName()}: " . $e->getMessage());
            }
        }
    }

    /**
     * Process a discovered structure for the theme.
     *
     * @param mixed $structure The discovered structure
     * @param string $scoutType The type of scout that discovered it
     * @param ThemeModuleInterface $theme The theme being processed
     */
    protected function processDiscoveredStructure($structure, string $scoutType, ThemeModuleInterface $theme): void
    {
        // For now, we just log the discovery
        // In the future, this could trigger automatic registration
        if (function_exists('error_log') && defined('WP_DEBUG') && WP_DEBUG) {
            error_log("Discovered {$scoutType} in theme {$theme->getName()}: " .
                     (is_object($structure) && method_exists($structure, 'getFqn') ? $structure->getFqn() : 'unknown'));
        }

        // TODO: Add specific processing based on scout type
        // For example:
        // - Service providers could be automatically registered
        // - Post types could be automatically registered
        // - Hooks could be automatically instantiated
        // - etc.
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
        if ($this->activeTheme === null) {
            return false;
        }

        return $this->activeTheme->getLowerName() === strtolower($themeName);
    }

    /**
     * Reset the active theme registration.
     */
    public function resetActiveTheme(): void
    {
        $this->activeTheme = null;
        
        // Also invalidate repository cache when resetting
        $this->invalidateRepositoryCache();
    }
}
