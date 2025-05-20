<?php

declare(strict_types=1);

namespace Pollora\Theme\Infrastructure\Services;

use Pollora\Theme\Domain\Contracts\ThemeComponent;
use Psr\Container\ContainerInterface;

/**
 * Factory responsible for creating theme component instances with proper dependency injection.
 */
class ComponentFactory
{
    /**
     * @param ContainerInterface $app The Laravel application container
     */
    public function __construct(
        protected ContainerInterface $app
    ) {}

    /**
     * Create a new component instance using Laravel's container for dependency injection.
     *
     * @param string $component The fully qualified class name of the component
     * @return ThemeComponent The instantiated component
     */
    public function make(string $component): ThemeComponent
    {
        // This simple call will properly resolve all dependencies through Laravel's container
        return $this->app->get($component);
    }
}
