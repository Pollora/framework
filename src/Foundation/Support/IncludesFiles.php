<?php

declare(strict_types=1);

namespace Pollora\Foundation\Support;

use Symfony\Component\Finder\Finder;

/**
 * Trait providing file inclusion functionality.
 *
 * This trait provides methods to automatically include PHP files from specified
 * directories. It uses Symfony's Finder component to locate and sort files,
 * making it easy to include multiple files in a consistent order.
 */
trait IncludesFiles
{
    /**
     * Automatically includes all PHP files found in specified directories.
     *
     * Recursively searches through the given path(s) and includes all files
     * matching the specified pattern. Files are included in alphabetical order
     * to ensure consistent loading sequence.
     *
     * @param  string|array  $path  Single directory path or array of paths to search
     * @param  string  $pattern  File pattern to match (defaults to '*.php')
     *
     * @example
     * ```php
     * // Include all PHP files from a single directory
     * $this->includes(__DIR__ . '/includes');
     *
     * // Include files from multiple directories
     * $this->includes([
     *     __DIR__ . '/includes',
     *     __DIR__ . '/modules'
     * ]);
     *
     * // Include only specific files
     * $this->includes(__DIR__ . '/config', '*.config.php');
     * ```
     */
    public function includes($path, string $pattern = '*.php'): void
    {
        foreach (Finder::create()->files()->name($pattern)->in($path)->sortByName() as $file) {
            /** @var \SplFileInfo $file */
            @include $file->getRealPath();
        }
    }
}
