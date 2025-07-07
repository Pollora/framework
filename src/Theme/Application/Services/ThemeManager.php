<?php

declare(strict_types=1);

namespace Pollora\Theme\Application\Services;

use Illuminate\Contracts\Translation\Loader;
use Illuminate\Support\Str;
use Illuminate\View\ViewFinderInterface;
use Pollora\Application\Application\Services\ConsoleDetectionService;
use Pollora\Collection\Domain\Contracts\CollectionInterface;
use Pollora\Foundation\Support\IncludesFiles;
use Pollora\Modules\Domain\Contracts\ModuleRepositoryInterface;
use Pollora\Theme\Domain\Contracts\ThemeModuleInterface;
use Pollora\Theme\Domain\Contracts\ThemeRegistrarInterface;
use Pollora\Theme\Domain\Contracts\ThemeService;
use Pollora\Theme\Domain\Exceptions\ThemeException;
use Pollora\Theme\Domain\Models\ThemeMetadata;
use Psr\Container\ContainerInterface;

class ThemeManager implements ThemeService
{
    public $discovery;

    use IncludesFiles;

    protected array $config;

    protected array $parentThemes = [];

    protected ?ThemeMetadata $theme = null;

    protected ConsoleDetectionService $consoleDetectionService;

    public function __construct(
        protected ContainerInterface $app,
        protected ViewFinderInterface $viewFinder,
        protected ?Loader $localeLoader,
        protected ?ModuleRepositoryInterface $repository = null,
        protected ?ThemeRegistrarInterface $registrar = null,
        ?ConsoleDetectionService $consoleDetectionService = null
    ) {
        $this->consoleDetectionService = $consoleDetectionService ?? app(ConsoleDetectionService::class);
    }

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
            if (! is_dir($currentTheme->getBasePath()) && ! $this->consoleDetectionService->isConsole()) {
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

    // New modular theme management methods

    /**
     * Get all available themes as modules.
     */
    public function getAllThemes(): ?CollectionInterface
    {
        return $this->repository?->toCollection();
    }

    /**
     * Get all available themes as array.
     */
    public function getAllThemesAsArray(): array
    {
        return $this->repository?->all() ?? [];
    }

    /**
     * Get enabled themes.
     */
    public function getEnabledThemes(): array
    {
        return $this->repository?->allEnabled() ?? [];
    }

    /**
     * Get disabled themes.
     */
    public function getDisabledThemes(): array
    {
        return $this->repository?->allDisabled() ?? [];
    }

    /**
     * Find theme module by name.
     */
    public function findTheme(string $name): ?ThemeModuleInterface
    {
        if (! $this->repository instanceof \Pollora\Modules\Domain\Contracts\ModuleRepositoryInterface) {
            return null;
        }

        $theme = $this->repository->find($name);

        return $theme instanceof ThemeModuleInterface ? $theme : null;
    }

    /**
     * Get currently active theme as module.
     */
    public function getActiveTheme(): ?ThemeModuleInterface
    {
        return $this->registrar?->getActiveTheme();
    }

    /**
     * Check if theme exists.
     */
    public function hasTheme(string $name): bool
    {
        return $this->repository?->has($name) ?? false;
    }

    /**
     * Check if theme is active.
     */
    public function isThemeActive(string $name): bool
    {
        return $this->registrar?->isThemeActive($name) ?? false;
    }

    /**
     * Get theme information.
     */
    public function getThemeInfo(string $name): array
    {
        $theme = $this->findTheme($name);

        if (! $theme instanceof \Pollora\Theme\Domain\Contracts\ThemeModuleInterface) {
            throw ThemeException::notFound($name);
        }

        return [
            'name' => $theme->getName(),
            'description' => $theme->getDescription(),
            'version' => $theme->getVersion(),
            'author' => $theme->getAuthor(),
            'path' => $theme->getPath(),
            'enabled' => $theme->isEnabled(),
            'active' => $this->isThemeActive($name),
            'stylesheet' => $theme->getStylesheet(),
            'template' => $theme->getTemplate(),
            'is_child_theme' => $theme->isChildTheme(),
            'parent_theme' => $theme->getParentTheme(),
            'screenshot' => $theme->getScreenshot(),
            'theme_uri' => $theme->getThemeUri(),
            'author_uri' => $theme->getAuthorUri(),
        ];
    }

    /**
     * Scan for new themes.
     */
    public function scanThemes(): array
    {
        return $this->repository?->scan() ?? [];
    }

    /**
     * Register all themes.
     */
    public function registerThemes(): void
    {
        $this->repository?->register();
    }

    /**
     * Get theme count.
     */
    public function getThemeCount(): int
    {
        return $this->repository?->count() ?? 0;
    }

    /**
     * Validate theme structure.
     */
    public function validateTheme(string $name): array
    {
        $theme = $this->findTheme($name);

        if (! $theme instanceof \Pollora\Theme\Domain\Contracts\ThemeModuleInterface) {
            return [
                'valid' => false,
                'errors' => ['Theme not found'],
            ];
        }

        $errors = [];
        $path = $theme->getPath();

        // Check if directory exists
        if (! is_dir($path)) {
            $errors[] = 'Theme directory does not exist';
        }

        // Check required files
        $requiredFiles = ['style.css', 'index.php'];
        foreach ($requiredFiles as $file) {
            if (! file_exists($path.'/'.$file)) {
                $errors[] = "Missing required file: {$file}";
            }
        }

        return [
            'valid' => $errors === [],
            'errors' => $errors,
        ];
    }

    /**
     * Reset theme cache.
     */
    public function resetCache(): void
    {
        $this->repository?->resetCache();
        if (method_exists($this->discovery, 'resetCache')) {
            $this->discovery->resetCache();
        }
    }
}
