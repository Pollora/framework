<?php

declare(strict_types=1);

namespace Pollora\Modules\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Pollora\Modules\UI\Console\Commands\MakeModelCommand;

class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->registerCommands();
    }

    public function boot(): void
    {
        // Any additional boot logic for infrastructure layer
    }

    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                // Generic make commands
                MakeModelCommand::class,
            ]);
        }
    }
}
