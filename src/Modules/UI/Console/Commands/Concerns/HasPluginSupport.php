<?php

declare(strict_types=1);

namespace Pollora\Modules\UI\Console\Commands\Concerns;

use Symfony\Component\Console\Input\InputOption;

trait HasPluginSupport
{
    /**
     * Get the console command options.
     */
    protected function getOptions(): array
    {
        return array_merge(parent::getOptions() ?? [], [
            ['plugin', null, InputOption::VALUE_OPTIONAL, 'The plugin to generate the class in'],
        ]);
    }

    /**
     * Get the plugin name from option.
     */
    protected function getPluginOption(): ?string
    {
        return $this->option('plugin');
    }

    /**
     * Check if plugin option is specified.
     */
    protected function hasPluginOption(): bool
    {
        return $this->option('plugin') !== null;
    }

    /**
     * Resolve plugin name.
     */
    protected function resolvePlugin(): ?string
    {
        return $this->getPluginOption();
    }
}
