<?php

declare(strict_types=1);

namespace Pollora\Block\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Pollora\Block\Domain\Contracts\BlockRegistrarInterface;
use Pollora\Block\Infrastructure\Services\BlockRegistrar;
use Pollora\Block\UI\Console\MakeBlockCommand;
use Pollora\BlockCategory\Application\Services\BlockCategoryService;
use Pollora\BlockCategory\Domain\Contracts\BlockCategoryRegistrarInterface;
use Pollora\BlockCategory\Domain\Contracts\BlockCategoryServiceInterface;
use Pollora\BlockCategory\Infrastructure\Registrars\BlockCategoryRegistrar;
use Pollora\BlockPattern\Application\Services\PatternService;
use Pollora\BlockPattern\Domain\Contracts\PatternCategoryRegistrarInterface;
use Pollora\BlockPattern\Domain\Contracts\PatternDataExtractorInterface;
use Pollora\BlockPattern\Domain\Contracts\PatternRegistrarInterface;
use Pollora\BlockPattern\Domain\Contracts\PatternServiceInterface;
use Pollora\BlockPattern\Infrastructure\Adapters\WordPressPatternDataExtractor;
use Pollora\BlockPattern\Infrastructure\Registrars\WordPressPatternCategoryRegistrar;
use Pollora\BlockPattern\Infrastructure\Registrars\WordPressPatternRegistrar;
use Pollora\Hook\Domain\Contract\Action;

/**
 * Service provider for Gutenberg blocks, block categories, and block patterns.
 *
 * Consolidates registration of all block-related services:
 * - Block registration and scaffolding
 * - Block category registration
 * - Block pattern registration with category support
 */
class BlockServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->registerBlockServices();
        $this->registerBlockCategoryServices();
        $this->registerBlockPatternServices();

        if ($this->app->runningInConsole()) {
            $this->commands([MakeBlockCommand::class]);
        }
    }

    public function boot(): void
    {
        $this->publishStubs();

        // Register block categories from configuration
        $this->app->make(BlockCategoryServiceInterface::class)->registerConfiguredCategories();

        // Register block patterns on init
        $action = $this->app->get(Action::class);
        $action->add('init', function (): void {
            $this->app->make(PatternServiceInterface::class)->registerAll();
        });
    }

    private function registerBlockServices(): void
    {
        $this->app->singleton(BlockRegistrar::class);
        $this->app->alias(BlockRegistrar::class, BlockRegistrarInterface::class);
    }

    private function registerBlockCategoryServices(): void
    {
        $this->app->bind(BlockCategoryRegistrarInterface::class, BlockCategoryRegistrar::class);
        $this->app->bind(BlockCategoryServiceInterface::class, BlockCategoryService::class);
    }

    private function registerBlockPatternServices(): void
    {
        $this->app->bind(PatternDataExtractorInterface::class, WordPressPatternDataExtractor::class);
        $this->app->bind(PatternCategoryRegistrarInterface::class, WordPressPatternCategoryRegistrar::class);
        $this->app->bind(PatternRegistrarInterface::class, WordPressPatternRegistrar::class);
        $this->app->bind(PatternServiceInterface::class, PatternService::class);
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
