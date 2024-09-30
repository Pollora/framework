<?php

declare(strict_types=1);

/**
 * Class Theme
 *
 *
 * {@inheritDoc}
 */

namespace Pollen\Theme;

use Illuminate\Container\Container;
use Illuminate\Contracts\Translation\Loader;
use Illuminate\View\ViewFinderInterface;

class ThemeManager
{
    protected Container $app;

    protected ViewFinderInterface $viewFinder;

    protected Loader $localeLoader;

    protected array $config;

    protected array $parentThemes = [];

    public function __construct(Container $app, ViewFinderInterface $finder, Loader $localeLoader)
    {
        $this->app = $app;
        $this->viewFinder = $finder;
        $this->localeLoader = $localeLoader;
    }

    public function load(string $themeName): void
    {
        if (empty($themeName)) {
            throw new ThemeException('Theme name cannot be empty.');
        }

        $baseTheme = new ThemeMetadata($themeName, $this->getThemesPath());

        $currentTheme = $baseTheme;

        while (true) {
            if (! is_dir($currentTheme->getBasePath())) {
                throw new ThemeException("Theme directory {$currentTheme->getName()} not found.");
            }

            $currentTheme->loadConfiguration();

            $this->registerThemeDirectories($currentTheme);

            $parentThemeName = $currentTheme->getParentTheme();

            if (empty($parentThemeName)) {
                break;
            }

            $currentTheme = new ThemeMetadata($parentThemeName, $this->getThemesPath());
        }

        $this->localeLoader->addNamespace($themeName, $baseTheme->getLanguagePath());
    }

    protected function registerThemeDirectories(ThemeMetadata $theme): void
    {
        $this->viewFinder->addLocation($theme->getBasePath());
        $this->viewFinder->addLocation($theme->getViewPath());
    }

    public function getAvailableThemes(): array
    {
        $path = $this->getThemesPath();

        if (! file_exists($path)) {
            return [];
        }

        return array_filter(scandir($path), function ($entry) use ($path) {
            if ($entry === '.' || $entry === '..') {
                return false;
            }
            $themeInfo = new ThemeMetadata($entry, $path);

            return file_exists($themeInfo->getConfigPath());
        });
    }

    protected function getThemesPath(): string
    {
        return rtrim($this->app['config']->get('theme.path', base_path('themes')), '/');
    }

    public function active(): string
    {
        return get_stylesheet();
    }

    public function parent(): string
    {
        return get_stylesheet();
    }

    public function path(string $path): string
    {
        $theme = $this->active();

        return $this->getThemesPath().'/'.$theme.'/'.ltrim($path, '/');
    }
}
