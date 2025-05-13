<?php

declare(strict_types=1);

namespace Pollora\Gutenberg\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Pollora\Gutenberg\Application\Services\PatternRegistrationService;
use Pollora\Gutenberg\Domain\Contracts\CollectionFactoryInterface;
use Pollora\Gutenberg\Domain\Contracts\ConfigRepositoryInterface;
use Pollora\Gutenberg\Infrastructure\Services\LaravelCollectionFactory;
use Pollora\Gutenberg\Infrastructure\Services\LaravelConfigRepository;
use Pollora\Gutenberg\Registrars\BlockCategoryRegistrar;
use Pollora\Gutenberg\Registrars\PatternCategoryRegistrar;
use Pollora\Gutenberg\Registrars\PatternRegistrar;

/**
 * Service provider for Gutenberg feature bindings.
 *
 * Registers infrastructure implementations for domain contracts.
 */
class GutenbergServiceProvider extends ServiceProvider
{
    /**
     * Register Gutenberg feature bindings.
     */
    public function register(): void
    {
        $this->app->bind(ConfigRepositoryInterface::class, LaravelConfigRepository::class);
        $this->app->bind(CollectionFactoryInterface::class, LaravelCollectionFactory::class);
        $this->app->bind(
            PatternRegistrationService::class,
            function ($app) {
                return new PatternRegistrationService(
                    $app->make(PatternCategoryRegistrar::class),
                    $app->make(BlockCategoryRegistrar::class),
                    $app->make(PatternRegistrar::class)
                );
            }
        );
    }
}
