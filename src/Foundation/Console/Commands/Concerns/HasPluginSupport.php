<?php

declare(strict_types=1);

namespace Pollora\Foundation\Console\Commands\Concerns;

use Symfony\Component\Console\Input\InputOption;

trait HasPluginSupport
{
    /**
     * Get the console command options for plugin support.
     *
     * @return array The plugin-related command options
     */
    protected function getPluginOptions(): array
    {
        return [
            ['plugin', null, InputOption::VALUE_OPTIONAL, 'The plugin to generate the class in'],
        ];
    }

    /**
     * Get the plugin name from option.
     *
     * @return string|null The plugin name if specified
     */
    protected function getPluginOption(): ?string
    {
        return $this->option('plugin');
    }

    /**
     * Check if plugin option is specified.
     *
     * @return bool True if plugin option is set
     */
    protected function hasPluginOption(): bool
    {
        return $this->option('plugin') !== null;
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

        // Plugin paths would be resolved by plugin system
        // For now, throw exception as plugin system is not implemented yet
        throw new InvalidArgumentException('Plugin support is not yet implemented.');
    }
}
