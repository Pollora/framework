<?php

declare(strict_types=1);

namespace Pollora\Modules\UI\Console\Commands\Concerns;

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
}
