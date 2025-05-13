<?php

declare(strict_types=1);

namespace Pollora\Theme\Infrastructure\Services;

use Pollora\Container\Domain\ServiceLocator;
use Pollora\Theme\Domain\Contracts\ThemeComponent;

class ComponentFactory
{
    public function __construct(protected ServiceLocator $serviceLocator) {}

    public function make(string $component): ThemeComponent
    {
        return new $component($this->serviceLocator);
    }
}
