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
        $this->app->singleton(Action::class, Action::class);
        $this->app->singleton(Filter::class, Filter::class);

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
     * Load all configured hooks.
     *
     * Retrieves hooks from the container and registers each one individually.
     */
    protected function loadHooks(): void
    {
        $hooks = Discover::in(app_path('Cms/Hooks'))
            ->implementing(Hooks::class)
            ->classes()
            ->get();

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
        app()->make($hookClass);
    }
}
