<?php

declare(strict_types=1);

namespace Pollora\Theme\Infrastructure\Services;

use Pollora\Modules\Infrastructure\Services\ModuleAutoloader;
use Pollora\Theme\Domain\Models\ThemeModule;

/**
 * Simplified theme autoloader service.
 *
 * Handles autoloading for themes using the fixed namespace convention:
 * Theme\{ThemeName}\
 */
class ThemeAutoloader extends ModuleAutoloader
{
    /**
     * Register autoloading for a theme module.
     */
    public function registerThemeModule(ThemeModule $theme): void
    {
        $this->registerTheme($theme);
        $this->register();
    }

    /**
     * Register autoloading for multiple themes.
     */
    public function registerThemes(array $themes): void
    {
        foreach ($themes as $theme) {
            if ($theme instanceof ThemeModule) {
                $this->registerTheme($theme);
            }
        }

        $this->register();
    }

    /**
     * Get the expected namespace for a theme.
     */
    public function getThemeNamespace(string $themeName): string
    {
        return $this->buildNamespace($themeName, 'theme');
    }

    /**
     * Check if a theme namespace is registered.
     */
    public function isThemeRegistered(string $themeName): bool
    {
        $namespace = $this->getThemeNamespace($themeName);

        return $this->isNamespaceRegistered($namespace);
    }
}
