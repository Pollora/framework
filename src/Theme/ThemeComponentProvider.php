<?php

declare(strict_types=1);

namespace Pollora\Theme;

use Illuminate\Contracts\Foundation\Application;
use Pollora\Theme\Contracts\ThemeComponent;
use Pollora\Theme\Factories\ComponentFactory;

class ThemeComponentProvider
{
    protected $components = [
        \Pollora\Theme\ThemeInitializer::class,
        \Pollora\Gutenberg\Pattern::class,
        \Pollora\Theme\Menus::class,
        \Pollora\Theme\Support::class,
        \Pollora\Theme\Sidebar::class,
        \Pollora\Theme\Templates::class,
        \Pollora\Theme\ImageSize::class,
    ];

    public function __construct(
        protected Application $app,
        protected ComponentFactory $factory
    ) {}

    public function register(): void
    {
        foreach ($this->components as $component) {
            $this->app->singleton($component, fn (): \Pollora\Theme\Contracts\ThemeComponent => $this->factory->make($component));

            $instance = $this->app->make($component);
            if ($instance instanceof ThemeComponent) {
                $instance->register();
            }
        }
    }

    public function boot(): void {}
}
