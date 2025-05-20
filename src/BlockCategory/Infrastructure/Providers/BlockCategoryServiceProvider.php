<?php

declare(strict_types=1);

namespace Pollora\BlockCategory\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Pollora\BlockCategory\Application\Services\BlockCategoryService;
use Pollora\BlockCategory\Domain\Contracts\BlockCategoryRegistrarInterface;
use Pollora\BlockCategory\Domain\Contracts\BlockCategoryServiceInterface;
use Pollora\BlockCategory\Infrastructure\Registrars\BlockCategoryRegistrar;

/**
 * Service provider for BlockCategory feature bindings.
 *
 * Registers infrastructure implementations for domain contracts.
 */
class BlockCategoryServiceProvider extends ServiceProvider
{
    /**
     * Register BlockCategory feature bindings.
     */
    public function register(): void
    {
        // Bind domain interfaces to infrastructure implementations
        $this->app->bind(BlockCategoryRegistrarInterface::class, BlockCategoryRegistrar::class);
        
        // Bind application interfaces to application implementations
        $this->app->bind(BlockCategoryServiceInterface::class, BlockCategoryService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Trigger the application service to register configured categories
        $this->app->make(BlockCategoryServiceInterface::class)->registerConfiguredCategories();
    }
} 