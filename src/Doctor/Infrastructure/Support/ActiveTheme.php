<?php

declare(strict_types=1);

namespace Pollora\Doctor\Infrastructure\Support;

/**
 * The active WordPress theme, as the checks need it: its directory and its slug.
 * Null when WordPress is not loaded.
 */
final class ActiveTheme
{
    public static function directory(): ?string
    {
        if (! function_exists('get_stylesheet_directory')) {
            return null;
        }

        $directory = (string) get_stylesheet_directory();

        return $directory !== '' ? rtrim($directory, '/') : null;
    }

    public static function slug(): ?string
    {
        return function_exists('get_stylesheet') ? (string) get_stylesheet() : null;
    }

    /** The theme directory relative to the project, for a command a person can paste. */
    public static function relativeDirectory(): ?string
    {
        $directory = self::directory();

        if ($directory === null) {
            return null;
        }

        $base = rtrim(base_path(), '/').'/';

        return str_starts_with($directory, $base) ? substr($directory, strlen($base)) : $directory;
    }

    /**
     * Files under the theme directory with one of the extensions, dependencies and builds left out.
     *
     * @param  list<string>  $extensions
     * @return list<string> paths relative to the theme directory
     */
    public static function files(string $subdirectory, array $extensions, int $limit = 5000): array
    {
        $directory = self::directory();
        $root = $directory === null ? null : rtrim($directory.'/'.$subdirectory, '/');

        if ($root === null || ! is_dir($root)) {
            return [];
        }

        $iterator = new \RecursiveIteratorIterator(new \RecursiveCallbackFilterIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            static fn (\SplFileInfo $file): bool => ! in_array($file->getFilename(), ['node_modules', 'vendor', '.git'], true),
        ));

        $files = [];

        foreach ($iterator as $file) {
            if (in_array(strtolower($file->getExtension()), $extensions, true)) {
                $files[] = substr($file->getPathname(), strlen($directory) + 1);
            }

            if (count($files) >= $limit) {
                break;
            }
        }

        sort($files);

        return $files;
    }

    /**
     * Whether a pattern file has the Title and Slug WordPress needs to register it.
     */
    public static function patternHasHeader(string $file): bool
    {
        $header = (string) file_get_contents($file, false, null, 0, 8192);

        return preg_match('/^[ \t\/*#@]*Title:/mi', $header) === 1 && preg_match('/^[ \t\/*#@]*Slug:/mi', $header) === 1;
    }
}
