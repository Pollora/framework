<?php

declare(strict_types=1);

namespace Pollen\Hook;

use Illuminate\Support\Collection;
use Illuminate\Support\ServiceProvider;
use Pollen\Hook\Action;
use Pollen\Hook\Filter;

class HookServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadHooks();
    }

    public function register(): void
    {
        $this->app->bind('wp.action', function ($container) {
            return new Action($container);
        });

        $this->app->bind('wp.filter', function ($container) {
            return new Filter($container);
        });
    }

    protected function loadHooks(): void
    {
        collect(config('app.hooks'))->each(fn ($hook) => $this->registerHook($hook));
    }

    public function registerHook(string $hook): void
    {
        $instance = $this->app->make($hook);
        $hooks = (array) $instance->hook;

        if (method_exists($instance, 'register')) {
            empty($hooks)
                ? $instance->register()
                : $this->app->make('wp.action')->add(
                $hooks,
                [$instance, 'register'],
                $instance->priority
            );
        }
    }
}
