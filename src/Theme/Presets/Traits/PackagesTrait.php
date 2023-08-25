<?php

declare(strict_types=1);

namespace Pollen\Theme\Presets\Traits;

trait PackagesTrait
{
    /**
     * Add the "package.json" file.
     *
     * @return $this
     */
    protected function addPackages($dev = true)
    {
        copy($this->stubPath('tailwind-stubs/package.json'), $this->themePath('package.json'));

        return $this;
    }
}
