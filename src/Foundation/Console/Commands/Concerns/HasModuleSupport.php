<?php

declare(strict_types=1);

namespace Pollora\Foundation\Console\Commands\Concerns;

use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputOption;

/**
 * Trait to add module support to generator commands.
 * This trait allows generating files within a specific module.
 */
trait HasModuleSupport
{
    /**
     * Get the module options for the command.
     *
     * @return array<int, array<int, mixed>>
     */
    protected function getModuleOptions(): array
    {
        return [
            ['module', 'm', InputOption::VALUE_OPTIONAL, 'The module where the class should be generated'],
        ];
    }

    /**
     * Get the module name from the command options.
     */
    protected function getModuleName(): ?string
    {
        return $this->option('module');
    }

    /**
     * Check if the command is generating in a module.
     */
    protected function hasModuleOption(): bool
    {
        return $this->getModuleName() !== null;
    }

    /**
     * Get the module path.
     */
    protected function getModulePath(): string
    {
        $moduleName = $this->getModuleName();
        if ($moduleName === null) {
            return '';
        }

        return base_path('Modules/'.Str::studly($moduleName));
    }

    /**
     * Get the module namespace.
     */
    protected function getModuleNamespace(): string
    {
        $moduleName = $this->getModuleName();
        if ($moduleName === null) {
            return '';
        }

        return 'Modules\\'.Str::studly($moduleName);
    }

    /**
     * Get the module source path.
     */
    protected function getModuleSourcePath(): string
    {
        return $this->getModulePath().'/app';
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
