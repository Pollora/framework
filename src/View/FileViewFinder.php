<?php

declare(strict_types=1);

namespace Pollora\View;

use Illuminate\View\FileViewFinder as FileViewFinderBase;

class FileViewFinder extends FileViewFinderBase
{
    /**
     * Get possible relative locations of view files
     *
     * @param  string  $path  Absolute or relative path to possible view file
     * @return string[]
     */
    public function getPossibleViewFilesFromPath(string $path): array
    {
        $path = $this->getPossibleViewNameFromPath($path);

        return $this->getPossibleViewFiles($path);
    }

    /**
     * Get possible view name based on path
     *
     * @param  string  $file  Absolute or relative path to possible view file
     * @return string Possible view name
     */
    public function getPossibleViewNameFromPath(string $file): string
    {
        $namespace = null;
        $view = $this->normalizePath($file);
        $paths = $this->normalizePath($this->paths);
        $hints = array_map([$this, 'normalizePath'], $this->hints);

        $view = $this->stripExtensions($view);
        $view = str_replace($paths, '', $view);

        foreach ($hints as $hintNamespace => $hintPaths) {
            $test = str_replace($hintPaths, '', $view);
            if ($view !== $test) {
                $namespace = $hintNamespace;
                $view = $test;
                break;
            }
        }

        $view = ltrim($view, '/\\');

        if ($namespace !== null && $namespace !== 0 && ($namespace !== '' && $namespace !== '0')) {
            return "{$namespace}::$view";
        }

        return $view;
    }

    /**
     * Remove recognized extensions from path
     *
     * @param  string  $path  relative path to view file
     * @return string view name
     */
    protected function stripExtensions(string $path): string
    {
        $extensions = implode('|', array_map('preg_quote', $this->getExtensions()));

        return preg_replace("/\.({$extensions})$/", '', $path);
    }

    /**
     * Normalize paths
     *
     * @param  string|string[]  $path
     * @return string|string[]
     */
    protected function normalizePath($path, string $separator = '/'): string|array|null
    {
        return preg_replace('#[\\/]+#', $separator, $path);
    }
}
