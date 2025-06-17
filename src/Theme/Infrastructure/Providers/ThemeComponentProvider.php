<?php

declare(strict_types=1);

namespace Pollora\Theme\Infrastructure\Providers;

use Illuminate\Contracts\Foundation\Application;
use Pollora\BlockPattern\UI\PatternComponent;
use Pollora\Theme\Domain\Models\ImageSize;
use Pollora\Theme\Domain\Models\Menus;
use Pollora\Theme\Domain\Models\SelfRegisteredThemeInitializer;
use Pollora\Theme\Domain\Models\Sidebar;
use Pollora\Theme\Domain\Models\Templates;
use Pollora\Theme\Infrastructure\Services\Support;

/**
 * Provider responsible for registering and bootstrapping theme components.
 */
class ThemeComponentProvider
{
    /**
     * List of theme components to be registered.
     * Order matters - components are registered in the order defined here.
     */
    protected array $components = [
        SelfRegisteredThemeInitializer::class,
        PatternComponent::class,
        Menus::class,
        Support::class,
        Sidebar::class,
        Templates::class,
        ImageSize::class,
    ];

    /**
     * @param  Application|null  $app  The Laravel application instance (for direct resolution)
     */
    public function __construct(
        protected ?Application $app = null
    ) {}

    /**
     * Register all theme components.
     *
     * This will:
     * 1. Register each component in the service container
     * 2. Call the register() method on each component
     */
    public function register(): void
    {
        // Make sure we have an app instance
        if (! $this->app) {
            throw new \RuntimeException('Cannot register components: Application instance not available');
        }

        // Register and initialize each component
        foreach ($this->components as $component) {
            try {
                // Check if already bound to avoid redundant registrations
                if (! $this->app->bound($component)) {
                    // Simply register the component as a singleton
                    $this->app->singleton($component);
                }

                // Resolve from container (injection happens automatically)
                $instance = $this->app->make($component);

                // Register the component
                $instance->register();
            } catch (\Throwable $e) {
                // In debug mode, re-throw for easier debugging
                if (env('APP_DEBUG', false)) {
                    throw new \RuntimeException(
                        "Failed to register component {$component}: ".$e->getMessage(),
                        0,
                        $e
                    );
                }
            }
        }
    }

    /**
     * Add a component to the list.
     *
     * @param  string  $componentClass  The class name of the component to add
     */
    public function addComponent(string $componentClass): self
    {
        if (! in_array($componentClass, $this->components)) {
            $this->components[] = $componentClass;
        }

        return $this;
    }
}
