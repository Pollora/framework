<?php

declare(strict_types=1);

namespace Pollora\Modules\UI\Console\Commands\Concerns;

trait HasNameSupport
{
    /**
     * Get the class name input.
     * 
     * @return string The name of the class to generate
     */
    protected function getNameInput(): string
    {
        return trim($this->argument('name'));
    }
}
