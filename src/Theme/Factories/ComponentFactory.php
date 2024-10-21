<?php

declare(strict_types=1);

namespace Pollora\Theme\Factories;

use Pollora\Theme\Contracts\ThemeComponent;

class ComponentFactory
{
    public function __construct(protected \Illuminate\Contracts\Foundation\Application $app) {}

    public function make(string $class): ThemeComponent
    {
        return new $class($this->app);
    }
}
