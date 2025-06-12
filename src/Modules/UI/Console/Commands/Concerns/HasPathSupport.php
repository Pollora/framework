<?php

declare(strict_types=1);

namespace Pollora\Modules\UI\Console\Commands\Concerns;

use Symfony\Component\Console\Input\InputOption;

trait HasPathSupport
{
    /**
     * Get the console command options for path support.
     * 
     * @return array The path-related command options
     */
    protected function getPathOptions(): array
    {
        return [
            ['path', null, InputOption::VALUE_OPTIONAL, 'The custom path to generate the class in'],
        ];
    }

    /**
     * Get the custom path from option.
     * 
     * @return string|null The custom path if specified
     */
    protected function getPathOption(): ?string
    {
        return $this->option('path');
    }

    /**
     * Check if custom path option is specified.
     * 
     * @return bool True if path option is set
     */
    protected function hasPathOption(): bool
    {
        return $this->option('path') !== null;
    }

    /**
     * Resolve custom path.
     * 
     * @return string|null The resolved custom path
     */
    protected function resolvePath(): ?string
    {
        return $this->getPathOption();
    }
}
