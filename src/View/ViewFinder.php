<?php

declare(strict_types=1);

namespace Pollora\View;

use Illuminate\Support\Collection;
use Pollora\Filesystem\Filesystem;

class ViewFinder
{
    /**
     * Base path for theme or plugin in which views are located.
     *
     * @var string
     */
    protected $path;

    /**
     * Create new ViewFinder instance.
     *
     * @param  string  $path
     * @return void
     */
    public function __construct(/**
     * The FileViewFinder instance.
     */
    protected \Pollora\View\FileViewFinder $finder, /**
     * The Filesystem instance.
     */
    protected \Pollora\Filesystem\Filesystem $files, $path = '')
    {
        $this->path = $path ? realpath($path) : get_theme_file_path();
    }

    /**
     * Locate available view files.
     *
     * @return array
     */
    public function locate(mixed $file)
    {
        if (is_array($file)) {
            return array_merge(...array_map($this->locate(...), $file));
        }

        return $this->getRelativeViewPaths()
            ->flatMap(fn($viewPath) => collect($this->finder->getPossibleViewFilesFromPath($file))
                ->merge([$file])
                ->map(fn($file): string => "{$viewPath}/{$file}"))
            ->unique()
            ->map(fn($file): string => trim($file, '\\/'))
            ->toArray();
    }

    /**
     * Return the FileViewFinder instance.
     */
    public function getFinder(): \Pollora\View\FileViewFinder
    {
        return $this->finder;
    }

    /**
     * Return the Filesystem instance.
     */
    public function getFilesystem(): \Pollora\Filesystem\Filesystem
    {
        return $this->files;
    }

    /**
     * Get list of view paths relative to the base path
     *
     * @return Collection
     */
    protected function getRelativeViewPaths()
    {
        return collect($this->finder->getPaths())
            ->map(fn($viewsPath): string => $this->files->getRelativePath($this->path, $viewsPath));
    }
}
