<?php

declare(strict_types=1);

namespace Pollora\Foundation\Console\Commands\Concerns;

use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputOption;

trait HasPluginSupport
{
    const PLUGIN_OPTION = 'plugin';

    /**
     * Get the console command options for plugin support.
     *
     * @return array The plugin-related command options
     */
    protected function getPluginOptions(): array
    {
        return [
            [static::PLUGIN_OPTION, null, InputOption::VALUE_OPTIONAL, 'The plugin to generate the class in'],
        ];
    }

    /**
     * Get the plugin name from option.
     *
     * @return string|null The plugin name if specified
     */
    protected function getPluginOption(): ?string
    {
        return $this->option(static::PLUGIN_OPTION);
    }

    /**
     * Check if plugin option is specified.
     *
     * @return bool True if plugin option is set
     */
    protected function hasPluginOption(): bool
    {
        return $this->option(static::PLUGIN_OPTION) !== null;
    }

    /**
     * Resolve plugin name.
     *
     * @return string|null The resolved plugin name
     */
    protected function resolvePlugin(): ?string
    {
        return $this->getPluginOption();
    }

    /**
     * Resolve plugin name.
     *
     * @return string|null The resolved plugin name
     */
    protected function getPluginPath(): string
    {
        $pluginOpt = $this->resolvePlugin();
        if ($pluginOpt === null) {
            return '';
        }

        return WP_PLUGIN_DIR.DIRECTORY_SEPARATOR.$pluginOpt;
    }

    protected function getPluginNamespace()
    {
        $pluginOpt = $this->resolvePlugin();
        if ($pluginOpt === null) {
            return '';
        }

        return 'Plugins\\'.Str::studly($pluginOpt);
    }

    protected function getPluginSourcePath(): string
    {
        return $this->getPluginPath().'/app';
    }

    protected function getPluginSourceNamespace(): string
    {
        return $this->getPluginNamespace().'\\';
    }

    /**
     * Resolve plugin location.
     *
     * @return array{type: string, path: string, namespace: string, name: string}
     *
     * @throws InvalidArgumentException When plugin is not found or support not implemented
     */
    protected function resolvePluginLocation(): array
    {
        $plugin = $this->resolvePlugin();

        if (! $plugin) {
            throw new InvalidArgumentException('Plugin name cannot be empty when --plugin option is used.');
        }

        $location = [
            'type' => 'plugin',
            'path' => $this->getPluginPath(),
            'namespace' => $this->getPluginNamespace(),
            'source_path' => $this->getPluginSourcePath(),
            'source_namespace' => $this->getPluginSourceNamespace(),
        ];

        return $location;
    }
}
