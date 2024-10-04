<?php

declare(strict_types=1);

namespace Pollen\Filesystem;

use Illuminate\Filesystem\Filesystem as FilesystemBase;

class Filesystem extends FilesystemBase
{
    /**
     * Normalizes file path separators
     */
    public function normalizePath(string $path, string $separator = '/'): string
    {
        return preg_replace('#/+#', $separator, strtr($path, '\\', '/')) ?? $path;
    }

    /**
     * Get relative path of target from specified base
     *
     * @copyright Fabien Potencier
     * @license   MIT
     *
     * @link      https://github.com/symfony/routing/blob/v4.1.1/Generator/UrlGenerator.php#L280-L329
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
     * Ensure the path is relative
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
