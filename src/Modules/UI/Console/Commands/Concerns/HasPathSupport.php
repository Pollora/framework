<?php

declare(strict_types=1);

namespace Pollora\Modules\UI\Console\Commands\Concerns;

use Symfony\Component\Console\Input\InputOption;

trait HasPathSupport
{
    /**
     * Get the console command options.
     */
    protected function getOptions(): array
    {
        return array_merge(parent::getOptions() ?? [], [
            ['path', null, InputOption::VALUE_OPTIONAL, 'The custom path to generate the class in'],
        ]);
    }

    /**
     * Get the custom path from option.
     */
    protected function getPathOption(): ?string
    {
        return $this->option('path');
    }

    /**
     * Check if custom path option is specified.
     */
    protected function hasPathOption(): bool
    {
        return $this->option('path') !== null;
    }

    /**
     * Resolve custom path.
     */
    protected function resolvePath(): ?string
    {
        return $this->getPathOption();
    }
}
