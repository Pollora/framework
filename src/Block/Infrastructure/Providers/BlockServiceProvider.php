<?php

declare(strict_types=1);

namespace Pollora\Block\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Pollora\Block\Domain\Contracts\BlockRegistrarInterface;
use Pollora\Block\Infrastructure\Services\BlockRegistrar;
use Pollora\Block\UI\Console\MakeBlockCommand;

/**
 * Service provider for Gutenberg block registration and scaffolding.
 *
 * Registers the BlockRegistrar as a singleton and the MakeBlockCommand for console usage.
 */
class BlockServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(BlockRegistrar::class);
        $this->app->alias(BlockRegistrar::class, BlockRegistrarInterface::class);

        if ($this->app->runningInConsole()) {
            $this->registerCommands();
        }
    }

    public function boot(): void
    {
        $this->publishStubs();
    }

    private function registerCommands(): void
    {
        $this->commands([
            MakeBlockCommand::class,
        ]);
    }

    private function publishStubs(): void
    {
        $stubsPath = dirname(__DIR__, 2).'/stubs';

        if (is_dir($stubsPath)) {
            $this->publishes([
                $stubsPath => base_path('stubs/pollora-block'),
            ], 'pollora-block-stubs');
        }
    }
}
