<?php

declare(strict_types=1);

namespace Pollora\Foundation\Console\Commands\Concerns;

trait HasNameSupport
{
    const NAME_OPTION = 'name';

    /**
     * Get the class name input.
     *
     * @return string The name of the class to generate
     */
    protected function getNameInput(): string
    {
        return trim($this->argument(static::NAME_OPTION));
    }
}
