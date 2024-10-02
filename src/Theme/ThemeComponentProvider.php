<?php

declare(strict_types=1);

namespace Pollen\Theme;

use Illuminate\Contracts\Foundation\Application;
use Pollen\Theme\Contracts\ThemeComponent;
use Pollen\Theme\Factories\ComponentFactory;

class ThemeComponentProvider
{
    protected $components = [
        \Pollen\Theme\ThemeInitializer::class,
        \Pollen\Gutenberg\Pattern::class,
        \Pollen\Theme\Menus::class,
        \Pollen\Theme\Support::class,
        \Pollen\Theme\Sidebar::class,
        \Pollen\Theme\Templates::class,
        \Pollen\Theme\ImageSize::class,
    ];

    public function __construct(
        protected Application $app,
        protected ComponentFactory $factory
    ) {}

    public function register(): void
    {
        foreach ($this->components as $component) {
            $this->app->singleton($component, fn(): \Pollen\Theme\Contracts\ThemeComponent => $this->factory->make($component));

            $instance = $this->app->make($component);
            if ($instance instanceof ThemeComponent) {
                $instance->register();
            }
        }
    }

    public function boot(): void {}
}
