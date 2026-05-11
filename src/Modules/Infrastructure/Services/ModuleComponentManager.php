<?php

declare(strict_types=1);

namespace Pollora\Modules\Infrastructure\Services;

use Illuminate\Container\Container;
use Psr\Log\LoggerInterface;

/**
 * Generic module component manager.
 *
 * This service can manage components for any module type (themes, plugins, etc.)
 * providing a unified way to register and initialize module-specific components.
 */
class ModuleComponentManager
{
    protected array $registeredComponents = [];

    private ?LoggerInterface $logger = null;

    public function __construct(
        protected Container $app
    ) {
        try {
            $this->logger = $app->make(LoggerInterface::class);
        } catch (\Throwable) {
        }
    }

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
        } catch (\Throwable $throwable) {
            $this->logger?->error(sprintf('Failed to register component %s for module %s', $componentClass, $moduleId), ['exception' => $throwable]);
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
        } catch (\Throwable $throwable) {
            if (config('app.debug')) {
                throw new \RuntimeException(
                    sprintf('Failed to initialize component %s for module %s: ', $componentClass, $moduleId).$throwable->getMessage(),
                    0,
                    $throwable
                );
            }

            $this->logger?->error(sprintf('Component initialization failed: %s for module %s', $componentClass, $moduleId), ['exception' => $throwable]);
        }
    }

    /**
     * Get the service container key for a component.
     */
    protected function getComponentServiceKey(string $componentClass, string $moduleId): string
    {
        return sprintf('module.%s.component.', $moduleId).class_basename($componentClass);
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
