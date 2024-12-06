<?php

declare(strict_types=1);

namespace Pollora\Hook;

use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;

class HookServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('wp.hooks', fn (): array => $this->mergeHooksConfig());
        $this->app->singleton('wp.action', Action::class);
        $this->app->singleton('wp.filter', Filter::class);
    }

    public function boot(): void
    {
        $this->loadHooks();
    }

    protected function mergeHooksConfig(): array
    {
        $hookFile = $this->app->bootstrapPath('hooks.php');
        $bootstrapHooks = File::exists($hookFile) ? require $this->app->bootstrapPath('hooks.php') : [];
        $appConfigHooks = config('app.hooks', []);

        return array_merge($bootstrapHooks, $appConfigHooks);
    }

    protected function loadHooks(): void
    {
        $hooks = $this->app->make('wp.hooks');
        collect($hooks)->each(fn ($hook) => $this->registerHook($hook));
    }

    protected function registerHook(string $hookClass): void
    {
        $hook = $this->app->make($hookClass);

        if (method_exists($hook, 'register')) {
            $this->app->make('wp.action')->add(
                $hook->hook ?? [],
                [$hook, 'register'],
                $hook->priority ?? 10
            );
        }
    }
}
