<?php

declare(strict_types=1);

namespace Pollora\Theme\Infrastructure\Adapters;

use Pollora\Collection\Domain\Contracts\CollectionFactoryInterface;
use Pollora\Models\Option;
use Pollora\Theme\Domain\Contracts\ThemeModuleInterface;
use Pollora\Theme\Domain\Services\ThemeDiscoveryService;
use Pollora\Theme\Infrastructure\Services\WordPressThemeParser;

class WordPressThemeDiscovery extends ThemeDiscoveryService
{
    public function __construct(
        CollectionFactoryInterface $collectionFactory,
        protected WordPressThemeParser $themeParser
    ) {
        parent::__construct($collectionFactory);
    }

    protected function createThemeFromDirectory(string $name, string $path): ?ThemeModuleInterface
    {
        try {
            if (! $this->themeParser->validateThemeDirectory($path)) {
                return null;
            }

            return $this->themeParser->createThemeFromDirectory($name, $path);
        } catch (\Exception $e) {
            // Log error but continue discovery
            error_log("Failed to create theme from directory {$path}: ".$e->getMessage());

            return null;
        }
    }

    public function getThemePaths(): array
    {
        return [config('theme.directory', base_path('themes'))];
    }

    public function getActiveTheme(): ?ThemeModuleInterface
    {
        $activeThemeName = Option::where('option_name', 'stylesheet')->value('option_value');
        $themes = $this->discoverThemes();

        /** @var ThemeModuleInterface $theme */
        foreach ($themes as $theme) {
            if ($theme->getLowerName() === strtolower($activeThemeName)) {
                return $theme;
            }
        }

        return null;
    }

    protected function scanThemePath(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        // Use WordPress functions if available for better compatibility
        if (function_exists('search_theme_directories')) {
            $this->scanWithWordPressFunctions($path);
        } else {
            parent::scanThemePath($path);
        }
    }

    protected function scanWithWordPressFunctions(string $path): void
    {
        $directories = glob($path.'/*', GLOB_ONLYDIR);

        foreach ($directories as $themeDir) {
            $themeName = basename($themeDir);

            // Skip if already discovered
            if (isset($this->discoveredThemes[$themeName])) {
                continue;
            }

            // Use WordPress theme validation if available
            if ($this->isValidWordPressTheme($themeDir)) {
                $theme = $this->createThemeFromDirectory($themeName, $themeDir);
                if ($theme) {
                    $this->discoveredThemes[$themeName] = $theme;
                }
            }
        }
    }

    protected function isValidWordPressTheme(string $path): bool
    {
        // Use WordPress validation if available
        if (function_exists('wp_get_theme')) {
            try {
                $theme = wp_get_theme(basename($path));

                return $theme->exists() && ! $theme->errors();
            } catch (\Exception $e) {
                // Fall back to manual validation
            }
        }

        return $this->isValidThemeDirectory($path);
    }
}
