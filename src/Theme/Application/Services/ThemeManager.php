<?php

declare(strict_types=1);

/**
 * Class Theme
 *
 *
 * {@inheritDoc}
 */

namespace Pollora\Theme\Application\Services;

use Illuminate\Contracts\Translation\Loader;
use Illuminate\Support\Str;
use Illuminate\View\ViewFinderInterface;
use Pollora\Foundation\Support\IncludesFiles;
use Pollora\Theme\Domain\Contracts\ThemeService;
use Pollora\Theme\Domain\Exceptions\ThemeException;
use Pollora\Theme\Domain\Models\ThemeMetadata;
use Psr\Container\ContainerInterface;

class ThemeManager implements ThemeService
{
    use IncludesFiles;

    protected array $config;

    protected array $parentThemes = [];

    protected ?ThemeMetadata $theme = null;

    public function __construct(protected ContainerInterface $app, protected ViewFinderInterface $viewFinder, protected ?Loader $localeLoader) {}

    public function instance(): ThemeManager
    {
        return $this;
    }

    /**
     * Create a ThemeMetadata instance
     *
     * This method exists primarily to make testing easier
     */
    protected function createThemeMetadata(string $themeName, string $themesPath): ThemeMetadata
    {
        return new ThemeMetadata($themeName, $themesPath);
    }

    public function load(string $themeName): void
    {
        if ($themeName === '' || $themeName === '0') {
            throw new ThemeException('Theme name cannot be empty.');
        }

        $baseTheme = $this->createThemeMetadata($themeName, $this->getThemesPath());
        $this->theme = $baseTheme;
        $currentTheme = $baseTheme;

        while (true) {
            if (! is_dir($currentTheme->getBasePath()) && ! $this->app->runningInConsole()) {
                throw new ThemeException("Theme directory {$currentTheme->getName()} not found.");
            }

            $currentTheme->loadConfiguration();

            $this->registerThemeDirectories($currentTheme);

            $parentThemeName = $currentTheme->getParentTheme();

            if ($parentThemeName === null || $parentThemeName === '' || $parentThemeName === '0') {
                break;
            }

            $currentTheme = new ThemeMetadata($parentThemeName, $this->getThemesPath());
            $this->parentThemes[] = $currentTheme;
        }

        if ($this->localeLoader instanceof \Illuminate\Contracts\Translation\Loader) {
            $this->localeLoader->addNamespace($themeName, $baseTheme->getLanguagePath());
        }
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

    public function getThemeAppPath(string $themeName, string $path = ''): string
    {
        $themeNamespace = Str::studly($themeName);
        $path = trim($path, '/');

        if ($path !== '') {
            $segments = explode('/', $path, 2);
            if (count($segments) > 1) {
                return app_path($segments[0].'/'.$themeNamespace.'/'.$segments[1]);
            }
        }

        return app_path($path);
    }

    public function theme(): ?ThemeMetadata
    {
        return $this->theme;
    }

    public function getParentThemes(): array
    {
        return $this->parentThemes;
    }
}
