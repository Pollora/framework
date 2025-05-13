<?php

declare(strict_types=1);

namespace Pollora\Theme\Infrastructure\Providers;

use Illuminate\Contracts\Foundation\Application;
use Pollora\Container\Domain\ServiceLocator;
use Pollora\Gutenberg\UI\PatternComponent;
use Pollora\Theme\Domain\Contracts\ThemeComponent;
use Pollora\Theme\Domain\Models\ImageSize;
use Pollora\Theme\Domain\Models\Menus;
use Pollora\Theme\Domain\Models\Sidebar;
use Pollora\Theme\Domain\Models\Templates;
use Pollora\Theme\Domain\Models\ThemeInitializer;
use Pollora\Theme\Infrastructure\Services\ComponentFactory;
use Pollora\Theme\Infrastructure\Services\Support;

class ThemeComponentProvider
{
    protected array $components = [
        ThemeInitializer::class,
        PatternComponent::class,
        Menus::class,
        Support::class,
        Sidebar::class,
        Templates::class,
        ImageSize::class,
    ];

    public function __construct(
        protected ServiceLocator $locator,
        protected ComponentFactory $factory
    ) {}

    public function register(): void
    {
        $app = $this->locator->resolve(Application::class);

        foreach ($this->components as $component) {
            $app->singleton(
                $component,
                fn (): ThemeComponent => $this->factory->make($component)
            );

            $app->make($component)->register();
        }
    }

    // Suppression de la méthode boot() inutile
}
