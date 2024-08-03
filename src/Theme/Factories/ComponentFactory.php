<?php

declare(strict_types=1);

namespace Pollen\Theme\Factories;

use Illuminate\Contracts\Foundation\Application;
use Pollen\Theme\Contracts\ThemeComponent;

class ComponentFactory
{
    public function __construct(protected Application $app) {}

    public function make(string $class): ThemeComponent
    {
        return new $class($this->app);
    }
}
