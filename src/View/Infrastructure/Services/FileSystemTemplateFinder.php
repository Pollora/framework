<?php

declare(strict_types=1);

namespace Pollora\View\Infrastructure\Services;

use Illuminate\View\FileViewFinder;
use Illuminate\View\ViewFinderInterface;
use Pollora\Filesystem\Filesystem;
use Pollora\View\Domain\Contracts\TemplateFinderInterface;

/**
 * File system implementation of template finder.
 *
 * This implementation uses the file system to locate templates
 * and integrates with Laravel's ViewFinder for path resolution.
 */
class FileSystemTemplateFinder implements TemplateFinderInterface
{
    /**
     * Base path for theme in which views are located.
     */
    protected ?string $path = null;

    /**
     * Request-level cache for locate() results.
     *
     * @var array<string, array<string>>
     */
    private static array $locateCache = [];

    /**
     * Create new FileSystemTemplateFinder instance.
     */
    public function __construct(
        /**
         * The Laravel ViewFinder instance.
         */
        protected ViewFinderInterface $finder,
        /**
         * The Filesystem instance.
         */
        protected Filesystem $files,
        /**
         * Custom base path if provided.
         */
        protected string $customPath = ''
    ) {}

    /**
     * Get the theme base path, lazy-loaded to avoid early WordPress function calls.
     */
    protected function getThemePath(): string
    {
        if ($this->path === null) {
            if ($this->customPath !== '') {
                $this->path = realpath($this->customPath) ?: $this->customPath;
            } elseif (function_exists('get_theme_file_path')) {
                $this->path = realpath(get_theme_file_path()) ?: '';
            } else {
                $this->path = '';
            }
        }

        return $this->path;
    }

    /**
     * Locate template files from a list of template names.
     */
    public function locate($templateNames): array
    {
        if (is_array($templateNames)) {
            return array_merge(...array_map($this->locate(...), $templateNames));
        }

        // Return cached result if available
        if (isset(self::$locateCache[$templateNames])) {
            return self::$locateCache[$templateNames];
        }

        // Convert PHP template to Blade template
        $bladeTemplate = str_ends_with($templateNames, '.php') && ! str_ends_with($templateNames, '.blade.php')
            ? str_replace('.php', '.blade.php', $templateNames)
            : $templateNames;

        // Collected separately so that every Blade candidate outranks every PHP
        // one. The theme root is registered as a view path ahead of
        // resources/views, so ranking per path would let a theme's root
        // index.php — a stub WordPress requires for the theme to be valid —
        // shadow resources/views/index.blade.php and render nothing at all.
        $blade = [];
        $php = [];

        /** @var FileViewFinder $finder */
        $finder = $this->finder;
        foreach ($finder->getPaths() as $path) {
            $bladePath = $path.DIRECTORY_SEPARATOR.$bladeTemplate;
            if (file_exists($bladePath)) {
                $blade[] = $this->toThemeRelativePath($bladePath, $bladeTemplate);
            }

            // Check for original file if different from Blade
            if ($templateNames !== $bladeTemplate) {
                $originalPath = $path.DIRECTORY_SEPARATOR.$templateNames;
                if (file_exists($originalPath)) {
                    $php[] = $this->toThemeRelativePath($originalPath, $templateNames);
                }
            }
        }

        return self::$locateCache[$templateNames] = array_values(array_unique(array_filter([...$blade, ...$php])));
    }

    /**
     * Express a located file relative to the theme, the way WordPress expects.
     */
    private function toThemeRelativePath(string $absolutePath, string $fallback): string
    {
        $themePath = $this->getThemePath();

        if ($themePath === '' || $themePath === '0') {
            return $fallback;
        }

        return $this->files->getRelativePath($themePath.DIRECTORY_SEPARATOR, $absolutePath);
    }

    /**
     * Clear the request-level locate cache.
     */
    public static function clearLocateCache(): void
    {
        self::$locateCache = [];
    }

    /**
     * Check if a template exists.
     */
    public function exists(string $templateName): bool
    {
        return $this->locate($templateName) !== [];
    }

    /**
     * Get view name from a template file path.
     */
    public function getViewNameFromPath(string $filePath): ?string
    {
        $themePath = $this->getThemePath();

        // Remove the base path to get relative path
        $viewName = $themePath !== '' && $themePath !== '0' ? str_replace($themePath, '', $filePath) : $filePath;
        $viewName = trim($viewName, '/\\');

        // Remove view directory prefixes
        /** @var FileViewFinder $finder2 */
        $finder2 = $this->finder;
        foreach ($finder2->getPaths() as $viewPath) {
            if ($themePath !== '' && $themePath !== '0') {
                $relativePath = $this->files->getRelativePath($themePath.DIRECTORY_SEPARATOR, $viewPath);
                if (str_starts_with($viewName, $relativePath)) {
                    $viewName = substr($viewName, strlen($relativePath));
                    $viewName = trim($viewName, '/\\');
                    break;
                }
            }
        }

        // Convert path separators to dots and remove extension
        $viewName = str_replace(['/', '\\'], '.', $viewName);
        $viewName = preg_replace('/\.(blade\.)?php$/', '', $viewName);

        return in_array($viewName, ['', '0', []], true) ? null : $viewName;
    }

    /**
     * Convert template names to their Blade equivalents.
     */
    public function getBladeTemplates(array $templates): array
    {
        return array_map(fn (string $template): string => str_ends_with($template, '.php') && ! str_ends_with($template, '.blade.php')
            ? str_replace('.php', '.blade.php', $template)
            : $template, $templates);
    }
}
