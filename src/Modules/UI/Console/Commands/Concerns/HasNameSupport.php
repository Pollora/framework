<?php

declare(strict_types=1);

namespace Pollora\Modules\UI\Console\Commands\Concerns;

use Symfony\Component\Console\Input\InputArgument;

trait HasNameSupport
{
    /**
     * Get the console command arguments.
     */
    protected function getArguments(): array
    {
        return array_merge(parent::getArguments() ?? [], [
            ['name', InputArgument::REQUIRED, 'The name of the class'],
        ]);
    }

    /**
     * Get the class name.
     */
    protected function getNameInput(): string
    {
        return trim($this->argument('name'));
    }
}
