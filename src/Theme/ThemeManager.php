<?php

declare(strict_types=1);

/**
 * Class Theme
 *
 *
 * {@inheritDoc}
 */

namespace Pollora\Theme;

use Illuminate\Container\Container;
use Illuminate\Contracts\Translation\Loader;
use Illuminate\View\ViewFinderInterface;

class ThemeManager
{
    protected array $config;

    protected array $parentThemes = [];

    public function __construct(protected Container $app, protected ViewFinderInterface $viewFinder, protected Loader $localeLoader) {}

    public function load(string $themeName): void
    {
        if ($themeName === '' || $themeName === '0') {
            throw new ThemeException('Theme name cannot be empty.');
        }

        $baseTheme = new ThemeMetadata($themeName, $this->getThemesPath());

        $currentTheme = $baseTheme;

        while (true) {
            if (! is_dir($currentTheme->getBasePath()) && ! app()->runningInConsole()) {
                throw new ThemeException("Theme directory {$currentTheme->getName()} not found.");
            }

            $currentTheme->loadConfiguration();

            $this->registerThemeDirectories($currentTheme);

            $parentThemeName = $currentTheme->getParentTheme();

            if ($parentThemeName === null || $parentThemeName === '' || $parentThemeName === '0') {
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

        return array_filter(scandir($path), function ($entry) use ($path): bool {
            if ($entry === '.' || $entry === '..') {
                return false;
            }
            $themeInfo = new ThemeMetadata($entry, $path);

            return file_exists($themeInfo->getConfigPath());
        });
    }

    protected function getThemesPath(): string
    {
        return rtrim((string) $this->app['config']->get('theme.path', base_path('themes')), '/');
    }

    public function active(): string|bool
    {
        if (! function_exists('get_stylesheet')) {
            return false;
        }

        return get_stylesheet();
    }

    public function parent(): string
    {
        return get_template();
    }

    public function path(string $path): string
    {
        $theme = $this->active();

        return $this->getThemesPath().'/'.$theme.'/'.ltrim($path, '/');
    }
}
