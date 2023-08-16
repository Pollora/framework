<?php

declare(strict_types=1);

namespace Pollen\Hook;

use Illuminate\Support\Collection;
use Illuminate\Support\ServiceProvider;

class HookServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot(): void
    {
        $this->loadHooks();
    }

    /**
     * Register the application services.
     */
    public function register(): void
    {
        $this->app->bind('action', function ($container) {
            return new ActionBuilder($container);
        });

        $this->app->bind('filter', function ($container) {
            return new FilterBuilder($container);
        });
    }

    /**
     * Load all hooks from the configuration.
     */
    protected function loadHooks(): void
    {
        Collection::make(config('app.hooks'))->each(fn ($hook) => $this->registerHook($hook));
    }

    /**
     * Register the specified hook.
     *
     * @param  string  $hook The name of the hook.
     */
    public function registerHook(string $hook): void
    {
        $instance = new $hook($this->app);
        $hooks = (array) $instance->hook;

        if (method_exists($instance, 'register')) {
            empty($hooks)
                ? $instance->register()
                : $this['action']->add($hooks, [$instance, 'register'], $instance->priority);
        }
    }
}
