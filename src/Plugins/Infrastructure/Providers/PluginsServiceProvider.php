<?php
declare(strict_types=1);

namespace Pollora\Plugins\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Pollora\Plugins\UI\Console\PluginMakeCommand;

class PluginsServiceProvider extends ServiceProvider
{
    /**
     * Register the plugins services.
     */
    public function register(): void
    {
        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                PluginMakeCommand::class
            ]);
        }
    }
}
