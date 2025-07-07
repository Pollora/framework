<?php

declare(strict_types=1);

namespace Pollora\Theme\Infrastructure\Providers;

use Illuminate\Contracts\Foundation\Application;
use Pollora\BlockPattern\UI\PatternComponent;
use Pollora\Theme\Domain\Models\ImageSize;
use Pollora\Theme\Domain\Models\Menus;
use Pollora\Theme\Domain\Models\Sidebar;
use Pollora\Theme\Domain\Models\Templates;
use Pollora\Theme\Domain\Models\ThemeInitializer;
use Pollora\Theme\Infrastructure\Services\Support;

/**
 * Simplified provider for registering and bootstrapping theme components.
 */
class ThemeComponentProvider
{
    /**
     * Theme components to register (order matters).
     */
    protected array $components = [
        ThemeInitializer::class,
        PatternComponent::class,
        Menus::class,
        Support::class,
        Sidebar::class,
        Templates::class,
        ImageSize::class,
    ];

    public function __construct(protected Application $app) {}

    /**
     * Register all theme components.
     */
    public function register(): void
    {
        foreach ($this->components as $component) {
            $this->registerComponent($component);
        }
    }

    /**
     * Register a single component.
     */
    protected function registerComponent(string $component): void
    {
        try {
            if (! $this->app->bound($component)) {
                $this->app->singleton($component);
            }

            $instance = $this->app->make($component);
            $instance->register();
        } catch (\Throwable $e) {
            if (env('APP_DEBUG', false)) {
                throw new \RuntimeException(
                    "Failed to register component {$component}: ".$e->getMessage(),
                    0,
                    $e
                );
            }

            // Log error but continue in production
            if (function_exists('error_log')) {
                error_log("Theme component registration failed: {$component} - ".$e->getMessage());
            }
        }
    }

    /**
     * Add a component to the list.
     */
    public function addComponent(string $componentClass): self
    {
        if (! in_array($componentClass, $this->components)) {
            $this->components[] = $componentClass;
        }

        return $this;
    }
}
