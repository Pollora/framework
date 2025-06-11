<?php

declare(strict_types=1);

namespace Pollora\Theme\Domain\Services;

use Pollora\Collection\Domain\Contracts\CollectionFactoryInterface;
use Pollora\Collection\Domain\Contracts\CollectionInterface;
use Pollora\Models\Option;
use Pollora\Theme\Domain\Contracts\ThemeDiscoveryInterface;
use Pollora\Theme\Domain\Contracts\ThemeModuleInterface;

class ThemeDiscoveryService implements ThemeDiscoveryInterface
{
    protected array $themePaths = [];

    protected array $discoveredThemes = [];

    protected bool $cached = false;

    public function __construct(
        protected CollectionFactoryInterface $collectionFactory
    ) {}

    public function discoverThemes(): CollectionInterface
    {
        if ($this->cached && ! empty($this->discoveredThemes)) {
            return $this->collectionFactory->make($this->discoveredThemes);
        }

        $this->discoveredThemes = [];

        foreach ($this->getThemePaths() as $path) {
            $this->scanThemePath($path);
        }

        $this->cached = true;

        return $this->collectionFactory->make($this->discoveredThemes);
    }

    public function getActiveTheme(): ?ThemeModuleInterface
    {
        $activeThemeName = Option::where('option_name', 'stylesheet')->value('option_value');

        return $this->getTheme($activeThemeName);
    }

    public function isThemeActive(string $alias): bool
    {
        $activeTheme = $this->getActiveTheme();

        return $activeTheme && $activeTheme->getLowerName() === strtolower($alias);
    }

    public function getTheme(string $alias): ?ThemeModuleInterface
    {
        $themes = $this->discoverThemes();

        return $themes->first(function (ThemeModuleInterface $theme) use ($alias) {
            return $theme->getLowerName() === strtolower($alias);
        });
    }

    public function getThemePaths(): array
    {
        if (empty($this->themePaths)) {
            // Default WordPress themes path with fallbacks
            $paths = [];

            // Try WordPress functions if available
            if (function_exists('get_theme_root')) {
                $paths[] = get_theme_root();
            } elseif (defined('WP_CONTENT_DIR')) {
                $paths[] = WP_CONTENT_DIR.'/themes';
            } else {
                // Fallback to standard locations
                $paths[] = base_path('themes');
                $paths[] = public_path('themes');
            }

            $this->themePaths = array_filter($paths, function ($path) {
                return ! empty($path) && is_string($path);
            });
        }

        return $this->themePaths;
    }

    public function addThemePath(string $path): static
    {
        if (! in_array($path, $this->themePaths)) {
            $this->themePaths[] = $path;
            $this->cached = false; // Reset cache when paths change
        }

        return $this;
    }

    public function setThemePaths(array $paths): static
    {
        $this->themePaths = $paths;
        $this->cached = false;

        return $this;
    }

    protected function scanThemePath(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $directories = glob($path.'/*', GLOB_ONLYDIR);

        foreach ($directories as $themeDir) {
            $themeName = basename($themeDir);

            // Skip if already discovered
            if (isset($this->discoveredThemes[$themeName])) {
                continue;
            }

            if ($this->isValidThemeDirectory($themeDir)) {
                $theme = $this->createThemeFromDirectory($themeName, $themeDir);
                if ($theme) {
                    $this->discoveredThemes[$themeName] = $theme;
                }
            }
        }
    }

    protected function isValidThemeDirectory(string $path): bool
    {
        // A valid theme directory should have at least style.css and index.php
        return file_exists($path.'/style.css') && file_exists($path.'/index.php');
    }

    protected function createThemeFromDirectory(string $name, string $path): ?ThemeModuleInterface
    {
        // This will be implemented by infrastructure layer
        // Return null for now, infrastructure will override this
        return null;
    }

    public function resetCache(): static
    {
        $this->cached = false;
        $this->discoveredThemes = [];

        return $this;
    }
}
