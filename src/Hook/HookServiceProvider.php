<?php

declare(strict_types=1);

namespace Pollora\Hook;

use Illuminate\Support\ServiceProvider;
use Pollora\Hook\Commands\ActionMakeCommand;
use Pollora\Hook\Commands\FilterMakeCommand;
use Pollora\Hook\Contracts\Hooks;
use Spatie\StructureDiscoverer\Discover;

/**
 * Service provider for WordPress hook functionality.
 *
 * Manages the registration and bootstrapping of WordPress hooks system,
 * including actions and filters, within the Laravel application context.
 */
class HookServiceProvider extends ServiceProvider
{
    /**
     * Register hook-related services in the application.
     *
     * Binds hook configurations and hook implementations as singletons
     * in the application container.
     */
    public function register(): void
    {
        $this->app->singleton('wp.hooks', fn (): array => $this->mergeHooksConfig());
        $this->app->singleton('wp.action', Action::class);
        $this->app->singleton('wp.filter', Filter::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                ActionMakeCommand::class,
                FilterMakeCommand::class,
            ]);
        }
    }

    /**
     * Bootstrap hook services.
     *
     * Loads and registers all configured hooks after the application has booted.
     */
    public function boot(): void
    {
        $this->loadHooks();
    }

    /**
     * Merge hook configurations from multiple sources.
     *
     * Combines hooks defined in the bootstrap file and application config,
     * with bootstrap hooks taking precedence.
     *
     * @return array The merged hook configuration array
     */
    protected function mergeHooksConfig(): array
    {
        $bootstrapHooks = Discover::in(app_path('Cms/Hooks'))->implementing(Hooks::class)->classes()->get();
        $appConfigHooks = config('hooks', []);

        return array_merge($bootstrapHooks, $appConfigHooks);
    }

    /**
     * Load all configured hooks.
     *
     * Retrieves hooks from the container and registers each one individually.
     */
    protected function loadHooks(): void
    {
        $hooks = $this->app->make('wp.hooks');
        collect($hooks)->each(fn ($hook) => $this->registerHook($hook));
    }

    /**
     * Register an individual hook class.
     *
     * Creates an instance of the hook class and registers its 'register' method
     * as a WordPress action if the method exists.
     *
     * @param  string  $hookClass  The fully qualified class name of the hook to register
     */
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
