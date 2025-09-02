<?php

declare(strict_types=1);

namespace Pollora\BlockPattern\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Pollora\BlockPattern\Application\Services\PatternService;
use Pollora\BlockPattern\Domain\Contracts\PatternCategoryRegistrarInterface;
use Pollora\BlockPattern\Domain\Contracts\PatternDataExtractorInterface;
use Pollora\BlockPattern\Domain\Contracts\PatternRegistrarInterface;
use Pollora\BlockPattern\Domain\Contracts\PatternServiceInterface;
use Pollora\BlockPattern\Infrastructure\Adapters\WordPressPatternDataExtractor;
use Pollora\BlockPattern\Infrastructure\Registrars\WordPressPatternCategoryRegistrar;
use Pollora\BlockPattern\Infrastructure\Registrars\WordPressPatternRegistrar;
use Pollora\Hook\Domain\Contracts\Action;

/**
 * Service provider for BlockPattern feature bindings.
 *
 * Registers infrastructure implementations for domain contracts.
 */
class BlockPatternServiceProvider extends ServiceProvider
{
    /**
     * Register BlockPattern feature bindings.
     */
    public function register(): void
    {
        // Bind domain interfaces to infrastructure implementations
        $this->app->bind(PatternDataExtractorInterface::class, WordPressPatternDataExtractor::class);
        $this->app->bind(PatternCategoryRegistrarInterface::class, WordPressPatternCategoryRegistrar::class);
        $this->app->bind(PatternRegistrarInterface::class, WordPressPatternRegistrar::class);

        // Bind application interfaces to application implementations
        $this->app->bind(PatternServiceInterface::class, PatternService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $action = $this->app->get(Action::class);
        $action->add('init', function () {
            // Trigger the application service to register patterns and categories
            $this->app->make(PatternServiceInterface::class)->registerAll();
        });
    }
}
