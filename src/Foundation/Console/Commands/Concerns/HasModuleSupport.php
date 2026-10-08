<?php

declare(strict_types=1);

namespace Pollora\Foundation\Console\Commands\Concerns;

use Illuminate\Container\Container;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputOption;
use Throwable;

/**
 * Trait to add module support to generator commands.
 * This trait allows generating files within a specific module.
 */
trait HasModuleSupport
{
    use ResolvesSourceDirectory;

    const MODULE_OPTION = 'module';

    /**
     * Get the module options for the command.
     *
     * @return array<int, array<int, mixed>>
     */
    protected function getModuleOptions(): array
    {
        return [
            [static::MODULE_OPTION, null, InputOption::VALUE_OPTIONAL, 'The module where the class should be generated'],
        ];
    }

    /**
     * Get the module name from the command options.
     */
    protected function getModuleName(): ?string
    {
        return $this->option(static::MODULE_OPTION);
    }

    /**
     * Check if the command is generating in a module.
     */
    protected function hasModuleOption(): bool
    {
        return $this->getModuleName() !== null;
    }

    /**
     * Get the module path: where nwidart/laravel-modules found the module, else
     * Modules/<Studly>.
     */
    protected function getModulePath(): string
    {
        $moduleName = $this->getModuleName();
        if ($moduleName === null) {
            return '';
        }

        $modulePath = $this->findModule($moduleName)?->getPath();

        if (is_string($modulePath) && $modulePath !== '') {
            return rtrim($modulePath, '/');
        }

        return base_path('Modules/'.Str::studly($moduleName));
    }

    /**
     * Get the module namespace: the PSR-4 prefix its composer.json maps onto its
     * source directory, else Modules\<Studly>.
     *
     * A module is free to pick its namespace (`Module\BlocksDemo\`), and a class
     * generated under another one is never autoloaded.
     */
    protected function getModuleNamespace(): string
    {
        $moduleName = $this->getModuleName();
        if ($moduleName === null) {
            return '';
        }

        return $this->readModuleSourceNamespace($this->getModulePath())
            ?? 'Modules\\'.Str::studly($moduleName);
    }

    /**
     * Find a module by the name given on the command line.
     */
    protected function findModule(string $moduleName): ?object
    {
        $container = Container::getInstance();

        if (! $container->bound('modules')) {
            return null;
        }

        try {
            $modules = $container->make('modules');

            return $modules->find($moduleName) ?? $modules->find(Str::studly($moduleName));
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Read the PSR-4 prefix a module's composer.json maps onto its source directory.
     */
    protected function readModuleSourceNamespace(string $modulePath): ?string
    {
        $composerFile = $modulePath.'/composer.json';

        if (! is_file($composerFile)) {
            return null;
        }

        $composer = json_decode((string) file_get_contents($composerFile), true);
        $psr4 = is_array($composer) ? ($composer['autoload']['psr-4'] ?? []) : [];

        if (! is_array($psr4)) {
            return null;
        }

        $sourceDirectory = basename($this->resolveSourceDirectory($modulePath));

        foreach ($psr4 as $prefix => $paths) {
            foreach ((array) $paths as $path) {
                if (is_string($prefix) && is_string($path) && trim($path, './') === $sourceDirectory) {
                    return rtrim($prefix, '\\');
                }
            }
        }

        return null;
    }

    /**
     * Get the module source path.
     */
    protected function getModuleSourcePath(): string
    {
        return $this->resolveSourceDirectory($this->getModulePath());
    }

    /**
     * Get the module source namespace.
     */
    protected function getModuleSourceNamespace(): string
    {
        return $this->getModuleNamespace().'\\';
    }

    /**
     * Get the module configuration.
     */
    protected function resolveModuleLocation(): array
    {
        if (! $this->hasModuleOption()) {
            return [];
        }

        return [
            'type' => 'module',
            'path' => $this->getModulePath(),
            'namespace' => $this->getModuleNamespace(),
            'source_path' => $this->getModuleSourcePath(),
            'source_namespace' => $this->getModuleSourceNamespace(),
        ];
    }
}
