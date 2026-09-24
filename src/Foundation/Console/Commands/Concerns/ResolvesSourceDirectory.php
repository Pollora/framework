<?php

declare(strict_types=1);

namespace Pollora\Foundation\Console\Commands\Concerns;

/**
 * Pick the directory a theme, plugin or module keeps its classes in.
 *
 * The autoloaders map a namespace onto `app/` when it exists and onto `src/`
 * otherwise — one directory, never both (PluginAutoloader, ThemeRegistrar,
 * ModuleAutoloader). The generators used to write into `app/` unconditionally,
 * which on a `src/` target is worse than a misplaced file: creating `app/`
 * flips the autoloader and discovery over to it, and every class already in
 * `src/` stops being loaded.
 *
 * So generators write where the autoloader reads, with the same precedence.
 * A target with neither directory gets `app/`, the preferred layout.
 */
trait ResolvesSourceDirectory
{
    protected function resolveSourceDirectory(string $root): string
    {
        $root = rtrim($root, '/');

        if (! is_dir($root.'/app') && is_dir($root.'/src')) {
            return $root.'/src';
        }

        return $root.'/app';
    }
}
