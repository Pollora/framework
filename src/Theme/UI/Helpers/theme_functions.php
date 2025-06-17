<?php

declare(strict_types=1);

/**
 * Theme helper functions for self-registration.
 *
 * These functions provide a simple API for themes to register themselves
 * without requiring knowledge of the underlying framework architecture.
 */

use Pollora\Modules\Domain\Contracts\ModuleRepositoryInterface;
use Pollora\Theme\Domain\Contracts\ThemeModuleInterface;
use Pollora\Theme\Domain\Contracts\ThemeRegistrarInterface;

if (!function_exists('pollora_register_theme')) {
    /**
     * Register the current theme as the active theme.
     *
     * This function should be called from the theme's functions.php file
     * to declare the theme as active and initialize it.
     *
     * @param string $themeName The name of the theme (usually directory name)
     * @param string|null $themePath Optional path to theme directory (auto-detected if null)
     * @param array $themeData Optional theme metadata (parsed from style.css if empty)
     * @return ThemeModuleInterface The registered theme module
     *
     * @throws RuntimeException If the theme registrar service is not available
     */
    function pollora_register_theme(string $themeName, ?string $themePath = null, array $themeData = []): ThemeModuleInterface
    {
        // Auto-detect theme path if not provided
        if ($themePath === null) {
            $themePath = get_stylesheet_directory();
        }

        // Get the theme registrar service
        if (!function_exists('app') || !app()->bound(ThemeRegistrarInterface::class)) {
            throw new RuntimeException('Theme registrar service is not available. Make sure Pollora framework is properly initialized.');
        }

        /** @var ThemeRegistrarInterface $registrar */
        $registrar = app(ThemeRegistrarInterface::class);

        return $registrar->registerActiveTheme($themeName, $themePath, $themeData);
    }
}

if (!function_exists('pollora_get_active_theme')) {
    /**
     * Get the currently active theme.
     *
     * @return ThemeModuleInterface|null The active theme or null if none registered
     */
    function pollora_get_active_theme(): ?ThemeModuleInterface
    {
        if (!function_exists('app') || !app()->bound(ThemeRegistrarInterface::class)) {
            return null;
        }

        /** @var ThemeRegistrarInterface $registrar */
        $registrar = app(ThemeRegistrarInterface::class);

        return $registrar->getActiveTheme();
    }
}

if (!function_exists('pollora_is_theme_active')) {
    /**
     * Check if a specific theme is active.
     *
     * @param string $themeName The theme name to check
     * @return bool True if the theme is active
     */
    function pollora_is_theme_active(string $themeName): bool
    {
        if (!function_exists('app') || !app()->bound(ThemeRegistrarInterface::class)) {
            return false;
        }

        /** @var ThemeRegistrarInterface $registrar */
        $registrar = app(ThemeRegistrarInterface::class);

        return $registrar->isThemeActive($themeName);
    }
}

if (!function_exists('pollora_theme_path')) {
    /**
     * Get a path relative to the active theme directory.
     *
     * @param string $path The relative path within the theme
     * @return string The full path
     */
        function pollora_theme_path(string $path = ''): string
    {
        $activeTheme = pollora_get_active_theme();

        if ($activeTheme === null) {
            throw new RuntimeException('No active theme registered. Make sure to call pollora_register_theme() in your theme\'s functions.php file.');
        }

        return $activeTheme->getPath() . ($path ? '/' . ltrim($path, '/') : '');
    }
}

if (!function_exists('pollora_debug_theme_sync')) {
    /**
     * Debug function to check synchronization between registrar and repository.
     *
     * This function helps troubleshoot synchronization issues between the
     * ThemeRegistrar and ThemeRepository services.
     *
     * @return array Debug information about theme synchronization
     */
    function pollora_debug_theme_sync(): array
    {
        $debug = [
            'registrar_available' => false,
            'repository_available' => false,
            'registrar_theme' => null,
            'repository_themes' => [],
            'sync_status' => 'unknown'
        ];

        if (!function_exists('app')) {
            $debug['error'] = 'Laravel app() function not available';
            return $debug;
        }

        // Check registrar
        if (app()->bound(ThemeRegistrarInterface::class)) {
            $debug['registrar_available'] = true;
            try {
                /** @var ThemeRegistrarInterface $registrar */
                $registrar = app(ThemeRegistrarInterface::class);
                $activeTheme = $registrar->getActiveTheme();
                
                if ($activeTheme) {
                    $debug['registrar_theme'] = [
                        'name' => $activeTheme->getName(),
                        'path' => $activeTheme->getPath(),
                        'enabled' => $activeTheme->isEnabled()
                    ];
                }
            } catch (\Exception $e) {
                $debug['registrar_error'] = $e->getMessage();
            }
        }

        // Check repository
        if (app()->bound(ModuleRepositoryInterface::class)) {
            $debug['repository_available'] = true;
            try {
                /** @var ModuleRepositoryInterface $repository */
                $repository = app(ModuleRepositoryInterface::class);
                $themes = $repository->all();
                
                foreach ($themes as $theme) {
                    if ($theme instanceof ThemeModuleInterface) {
                        $debug['repository_themes'][] = [
                            'name' => $theme->getName(),
                            'path' => $theme->getPath(),
                            'enabled' => $theme->isEnabled()
                        ];
                    }
                }
            } catch (\Exception $e) {
                $debug['repository_error'] = $e->getMessage();
            }
        }

        // Determine sync status
        if ($debug['registrar_available'] && $debug['repository_available']) {
            $registrarThemeName = $debug['registrar_theme']['name'] ?? null;
            $repositoryThemeNames = array_column($debug['repository_themes'], 'name');
            
            if ($registrarThemeName && in_array($registrarThemeName, $repositoryThemeNames)) {
                $debug['sync_status'] = 'synchronized';
            } else {
                $debug['sync_status'] = 'out_of_sync';
            }
        }

        return $debug;
    }
}
