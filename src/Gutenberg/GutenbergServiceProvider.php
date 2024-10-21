<?php

declare(strict_types=1);

namespace Pollora\Gutenberg;

use Illuminate\Support\ServiceProvider;
use Pollora\Gutenberg\Helpers\PatternDataProcessor;
use Pollora\Gutenberg\Helpers\PatternValidator;
use Pollora\Gutenberg\Registrars\CategoryRegistrar;
use Pollora\Gutenberg\Registrars\PatternRegistrar;

class GutenbergServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CategoryRegistrar::class);
        $this->app->singleton(PatternRegistrar::class);
        $this->app->singleton(PatternDataProcessor::class);
        $this->app->singleton(PatternValidator::class);

        $this->app->when(PatternRegistrar::class)
            ->needs(PatternDataProcessor::class)
            ->give(fn($app) => $app->make(PatternDataProcessor::class));

        $this->app->when(PatternRegistrar::class)
            ->needs(PatternValidator::class)
            ->give(fn($app) => $app->make(PatternValidator::class));
    }
}
