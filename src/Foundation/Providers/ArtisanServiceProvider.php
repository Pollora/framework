<?php

declare(strict_types=1);

namespace Pollora\Foundation\Providers;

use Illuminate\Console\Application as Artisan;
use Illuminate\Support\ServiceProvider;
use Pollora\Foundation\Console\Commands\MakeModelCommand;
use Pollora\I18n\Tooling\Console\CompileCommand;
use Pollora\I18n\Tooling\Console\ExtractCommand;
use Pollora\I18n\Tooling\Console\UpdateCommand;

/**
 * Service provider for artisan commands.
 */
class ArtisanServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                MakeModelCommand::class,
            ]);

            $this->registerTranslationCommands();
        }
    }

    /**
     * Adds the commands of pollora/i18n (pollora:i18n:extract, :update and :compile) to Artisan.
     *
     * They are the package's own commands, so Artisan and the vendor/bin/pollora-i18n binary
     * accept the same options and print the same report. The short names the package gives
     * them for its binary (`extract`, `update`, `compile`) are dropped: they would be bare
     * Artisan commands. The Tooling classes load when Artisan starts, never on a web request.
     */
    private function registerTranslationCommands(): void
    {
        Artisan::starting(function (Artisan $artisan): void {
            foreach ([new ExtractCommand, new UpdateCommand, new CompileCommand] as $command) {
                $command->setAliases([]);
                $artisan->add($command);
            }
        });
    }
}
