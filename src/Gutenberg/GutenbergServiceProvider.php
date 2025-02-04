<?php

declare(strict_types=1);

namespace Pollora\Gutenberg;

use Illuminate\Support\ServiceProvider;
use Pollora\Gutenberg\Helpers\PatternDataProcessor;
use Pollora\Gutenberg\Helpers\PatternValidator;
use Pollora\Gutenberg\Registrars\PatternCategoryRegistrar;
use Pollora\Gutenberg\Registrars\BlockCategoryRegistrar;
use Pollora\Gutenberg\Registrars\PatternRegistrar;

/**
 * Service provider for Gutenberg block pattern functionality.
 *
 * Manages the registration of block pattern services and their dependencies
 * within the Laravel application container.
 */
class GutenbergServiceProvider extends ServiceProvider
{
    /**
     * Register Gutenberg-related services in the application.
     *
     * Binds pattern registrars and helpers as singletons in the container
     * and configures dependency injection for the PatternRegistrar.
     */
    public function register(): void
    {
        $this->app->singleton(PatternCategoryRegistrar::class);
        $this->app->singleton(BlockCategoryRegistrar::class);
        $this->app->singleton(PatternRegistrar::class);
        $this->app->singleton(PatternDataProcessor::class);
        $this->app->singleton(PatternValidator::class);

        $this->app->when(PatternRegistrar::class)
            ->needs(PatternDataProcessor::class)
            ->give(fn ($app) => $app->make(PatternDataProcessor::class));

        $this->app->when(PatternRegistrar::class)
            ->needs(PatternValidator::class)
            ->give(fn ($app) => $app->make(PatternValidator::class));
    }
}
