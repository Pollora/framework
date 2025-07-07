<?php

declare(strict_types=1);

namespace Pollora\Foundation\Console\Commands\Concerns;

use InvalidArgumentException;
use Pollora\Theme\Domain\Contracts\ThemeRegistrarInterface;
use Symfony\Component\Console\Input\InputOption;

trait HasThemeSupport
{
    /**
     * Get the console command options for theme support.
     *
     * @return array The theme-related command options
     */
    protected function getThemeOptions(): array
    {
        return [
            ['theme', null, InputOption::VALUE_OPTIONAL, 'The theme to generate the class in'],
        ];
    }

    /**
     * Get the theme name from option.
     *
     * @return string|null The theme name if specified
     */
    protected function getThemeOption(): ?string
    {
        return $this->option('theme');
    }

    /**
     * Check if theme option is specified.
     *
     * @return bool True if theme option is set
     */
    protected function hasThemeOption(): bool
    {
        if ($this->option('theme') !== null) {
            return true;
        }

        return (bool) $this->input->hasParameterOption(['--theme']);
    }

    /**
     * Get the active theme if no theme specified.
     *
     * @return string|null The active theme name
     */
    protected function getActiveTheme(): ?string
    {
        if (! property_exists($this, 'registrar') || $this->registrar === null) {
            $this->registrar = app(ThemeRegistrarInterface::class);
        }

        $activeTheme = $this->registrar?->getActiveTheme();

        if ($activeTheme) {
            return $activeTheme->getName();
        }

        return null;
    }

    /**
     * Get theme path for a given theme name.
     *
     * @param  string  $themeName  The theme name
     * @return string The theme path
     */
    protected function getThemePath(string $themeName): string
    {
        // Default themes path, can be overridden
        $themesPath = config('theme.path', base_path('themes'));

        return rtrim($themesPath, '/').'/'.$themeName;
    }

    /**
     * Normalize theme name for namespace.
     *
     * @param  string  $themeName  The theme name to normalize
     * @return string The normalized theme name for namespace
     */
    protected function normalizeThemeName(string $themeName): string
    {
        return str_replace(['-', '_', ' '], '', ucwords($themeName, '-_ '));
    }

    /**
     * Get the module namespace.
     */
    protected function getThemeNamespace(string $themeName): string
    {
        return 'Theme\\'.$this->normalizeThemeName($themeName);
    }

    /**
     * Get the module source path.
     */
    protected function getThemeSourcePath(string $themeName): string
    {
        return $this->getThemePath($themeName).'/app';
    }

    /**
     * Get the module source namespace.
     */
    protected function getThemeSourceNamespace(string $themeName): string
    {
        return $this->getThemeNamespace($themeName).'\\';
    }

    /**
     * Resolve theme name (from option or active theme).
     *
     * @return string|null The resolved theme name
     */
    protected function resolveTheme(): ?string
    {
        $theme = $this->getThemeOption();

        if ($theme === '') {
            // Empty string means use current active theme
            return $this->getActiveTheme();
        }

        return $theme;
    }

    /**
     * Resolve theme location.
     *
     * @return array{type: string, path: string, namespace: string, name: string}
     *
     * @throws InvalidArgumentException When theme is not found
     */
    protected function resolveThemeLocation(): array
    {
        $theme = $this->resolveTheme();

        if (! $theme) {
            $theme = $this->getActiveTheme();
        }

        if (! $theme) {
            throw new InvalidArgumentException('No theme specified and no active theme found.');
        }

        // Get theme path from theme system
        $themePath = $this->getThemePath($theme);

        if (! is_dir($themePath)) {
            throw new InvalidArgumentException("Theme directory not found: {$themePath}");
        }

        return [
            'type' => 'theme',
            'path' => $this->getThemePath($theme),
            'namespace' => 'Theme\\'.$this->normalizeThemeName($theme),
            'source_path' => $this->getThemeSourcePath($theme),
            'source_namespace' => $this->getThemeSourceNamespace($theme),
        ];
    }
}
