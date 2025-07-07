<?php

declare(strict_types=1);

namespace Pollora\Modules\Infrastructure\Services;

use Illuminate\Container\Container;

/**
 * Generic module component manager.
 *
 * This service can manage components for any module type (themes, plugins, etc.)
 * providing a unified way to register and initialize module-specific components.
 */
class ModuleComponentManager
{
    protected array $registeredComponents = [];

    public function __construct(
        protected Container $app
    ) {}

    /**
     * Register components for a specific module.
     *
     * @param  string  $moduleId  Unique identifier for the module
     * @param  array  $components  Array of component class names
     */
    public function registerModuleComponents(string $moduleId, array $components): void
    {
        $this->registeredComponents[$moduleId] = $components;

        foreach ($components as $componentClass) {
            $this->registerComponent($componentClass, $moduleId);
        }
    }

    /**
     * Initialize all components for a specific module.
     */
    public function initializeModuleComponents(string $moduleId): void
    {
        if (! isset($this->registeredComponents[$moduleId])) {
            return;
        }

        foreach ($this->registeredComponents[$moduleId] as $componentClass) {
            $this->initializeComponent($componentClass, $moduleId);
        }
    }

    /**
     * Register a single component.
     */
    protected function registerComponent(string $componentClass, string $moduleId): void
    {
        try {
            $serviceKey = $this->getComponentServiceKey($componentClass, $moduleId);

            if (! $this->app->bound($serviceKey)) {
                $this->app->singleton($serviceKey, $componentClass);
            }
        } catch (\Throwable $e) {
            if (function_exists('error_log')) {
                error_log("Failed to register component {$componentClass} for module {$moduleId}: ".$e->getMessage());
            }
        }
    }

    /**
     * Initialize a single component.
     */
    protected function initializeComponent(string $componentClass, string $moduleId): void
    {
        try {
            $serviceKey = $this->getComponentServiceKey($componentClass, $moduleId);

            if ($this->app->bound($serviceKey)) {
                $instance = $this->app->make($serviceKey);

                if (method_exists($instance, 'register')) {
                    $instance->register();
                }
            }
        } catch (\Throwable $e) {
            if (env('APP_DEBUG', false)) {
                throw new \RuntimeException(
                    "Failed to initialize component {$componentClass} for module {$moduleId}: ".$e->getMessage(),
                    0,
                    $e
                );
            }

            if (function_exists('error_log')) {
                error_log("Component initialization failed: {$componentClass} for module {$moduleId} - ".$e->getMessage());
            }
        }
    }

    /**
     * Get the service container key for a component.
     */
    protected function getComponentServiceKey(string $componentClass, string $moduleId): string
    {
        return "module.{$moduleId}.component.".class_basename($componentClass);
    }

    /**
     * Get all registered components for a module.
     */
    public function getModuleComponents(string $moduleId): array
    {
        return $this->registeredComponents[$moduleId] ?? [];
    }

    /**
     * Remove all components for a specific module.
     */
    public function unregisterModuleComponents(string $moduleId): void
    {
        if (isset($this->registeredComponents[$moduleId])) {
            foreach ($this->registeredComponents[$moduleId] as $componentClass) {
                $serviceKey = $this->getComponentServiceKey($componentClass, $moduleId);
                // Note: Laravel container doesn't have a direct way to unregister
                // but we can remove from our tracking
            }

            unset($this->registeredComponents[$moduleId]);
        }
    }
}
