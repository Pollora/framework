<?php

declare(strict_types=1);

namespace Pollora\Filesystem;

use Illuminate\Filesystem\Filesystem as FilesystemBase;

/**
 * Extended filesystem functionality for WordPress integration.
 *
 * This class extends Laravel's base Filesystem class to provide additional
 * functionality specifically designed for WordPress integration, including
 * path normalization and relative path calculation.
 *
 * @extends FilesystemBase
 */
class Filesystem extends FilesystemBase
{
    /**
     * Normalizes file path separators.
     *
     * Converts all directory separators to a consistent format, replacing
     * multiple consecutive separators with a single one.
     *
     * @param  string  $path  The file path to normalize
     * @param  string  $separator  The directory separator to use (defaults to '/')
     * @return string The normalized path
     *
     * @example
     * ```php
     * $fs = new Filesystem;
     * $path = $fs->normalizePath('path//to\\file'); // Returns 'path/to/file'
     * ```
     */
    public function normalizePath(string $path, string $separator = '/'): string
    {
        return preg_replace('#/+#', $separator, strtr($path, '\\', '/')) ?? $path;
    }

    /**
     * Get relative path of target from specified base.
     *
     * Calculates the relative path from a base directory to a target path,
     * useful for creating relative links between files or directories.
     *
     * @copyright Fabien Potencier
     * @license   MIT
     *
     * @link      https://github.com/symfony/routing/blob/v4.1.1/Generator/UrlGenerator.php#L280-L329
     *
     * @param  string  $basePath  The base directory path
     * @param  string  $targetPath  The target file/directory path
     * @return string The relative path from base to target
     *
     * @example
     * ```php
     * $fs = new Filesystem;
     * $relative = $fs->getRelativePath('/var/www', '/var/www/html/index.php');
     * // Returns 'html/index.php'
     * ```
     */
    public function getRelativePath(string $basePath, string $targetPath): string
    {
        $basePath = $this->normalizePath($basePath);
        $targetPath = $this->normalizePath($targetPath);

        if ($basePath === $targetPath) {
            return '';
        }

        $sourceDirs = explode('/', ltrim($basePath, '/'));
        $targetDirs = explode('/', ltrim($targetPath, '/'));
        array_pop($sourceDirs);
        $targetFile = array_pop($targetDirs);

        foreach ($sourceDirs as $i => $dir) {
            if (isset($targetDirs[$i]) && $dir === $targetDirs[$i]) {
                unset($sourceDirs[$i], $targetDirs[$i]);
            } else {
                break;
            }
        }

        $targetDirs[] = $targetFile;
        $path = str_repeat('../', count($sourceDirs)).implode('/', $targetDirs);

        return $this->ensureRelativePath($path);
    }

    /**
     * Ensure the path is relative.
     *
     * Ensures that a path is properly formatted as a relative path by adding
     * './' prefix when necessary.
     *
     * @param  string  $path  The path to process
     * @return string The ensured relative path
     *
     * @example
     * ```php
     * $fs = new Filesystem;
     * $path = $fs->ensureRelativePath('path/to/file');
     * // Returns 'path/to/file'
     * $path = $fs->ensureRelativePath('/path/to/file');
     * // Returns './path/to/file'
     * ```
     */
    private function ensureRelativePath(string $path): string
    {
        if ($path === '') {
            return './';
        }

        if ($path[0] === '/') {
            return ".{$path}";
        }

        $colonPos = strpos($path, ':');
        if ($colonPos !== false) {
            $slashPos = strpos($path, '/');
            if ($slashPos === false || $slashPos >= $colonPos) {
                return "./{$path}";
            }
        }

        return $path;
    }
}
