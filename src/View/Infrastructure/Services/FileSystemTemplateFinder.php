<?php

declare(strict_types=1);

namespace Pollora\View\Infrastructure\Services;

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
     * The Laravel ViewFinder instance.
     */
    protected ViewFinderInterface $finder;

    /**
     * The Filesystem instance.
     */
    protected Filesystem $files;

    /**
     * Base path for theme in which views are located.
     */
    protected ?string $path = null;

    /**
     * Custom base path if provided.
     */
    protected string $customPath;

    /**
     * Create new FileSystemTemplateFinder instance.
     */
    public function __construct(ViewFinderInterface $finder, Filesystem $files, string $path = '')
    {
        $this->finder = $finder;
        $this->files = $files;
        $this->customPath = $path;
    }

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
            return array_merge(...array_map([$this, 'locate'], $templateNames));
        }

        // Convert PHP template to Blade template
        $bladeTemplate = str_ends_with($templateNames, '.php') && ! str_ends_with($templateNames, '.blade.php')
            ? str_replace('.php', '.blade.php', $templateNames)
            : $templateNames;

        $found = [];

        // Check each view path for the template
        foreach ($this->finder->getPaths() as $path) {
            // Check for Blade version first
            $bladePath = $path.DIRECTORY_SEPARATOR.$bladeTemplate;
            if (file_exists($bladePath)) {
                $themePath = $this->getThemePath();
                if ($themePath) {
                    $found[] = $this->files->getRelativePath($themePath.DIRECTORY_SEPARATOR, $bladePath);
                } else {
                    $found[] = $bladeTemplate;
                }
            }

            // Check for original file if different from Blade
            if ($templateNames !== $bladeTemplate) {
                $originalPath = $path.DIRECTORY_SEPARATOR.$templateNames;
                if (file_exists($originalPath)) {
                    $themePath = $this->getThemePath();
                    if ($themePath) {
                        $found[] = $this->files->getRelativePath($themePath.DIRECTORY_SEPARATOR, $originalPath);
                    } else {
                        $found[] = $templateNames;
                    }
                }
            }
        }

        return array_unique(array_filter($found));
    }

    /**
     * Check if a template exists.
     */
    public function exists(string $templateName): bool
    {
        return ! empty($this->locate($templateName));
    }

    /**
     * Get view name from a template file path.
     */
    public function getViewNameFromPath(string $filePath): ?string
    {
        $themePath = $this->getThemePath();

        // Remove the base path to get relative path
        $viewName = $themePath ? str_replace($themePath, '', $filePath) : $filePath;
        $viewName = trim($viewName, '/\\');

        // Remove view directory prefixes
        foreach ($this->finder->getPaths() as $viewPath) {
            if ($themePath) {
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

        return $viewName ?: null;
    }

    /**
     * Convert template names to their Blade equivalents.
     */
    public function getBladeTemplates(array $templates): array
    {
        return array_map(function ($template) {
            return str_ends_with($template, '.php') && ! str_ends_with($template, '.blade.php')
                ? str_replace('.php', '.blade.php', $template)
                : $template;
        }, $templates);
    }
}
