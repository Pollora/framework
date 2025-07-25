<?php

declare(strict_types=1);

namespace Pollora\Option\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Pollora\Option\Application\Services\OptionService;
use Pollora\Option\Domain\Contracts\OptionRepositoryInterface;
use Pollora\Option\Domain\Services\OptionValidationService;
use Pollora\Option\Infrastructure\Repositories\WordPressOptionRepository;

/**
 * Service provider for the Option module.
 */
final class OptionServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(
            OptionRepositoryInterface::class,
            WordPressOptionRepository::class
        );

        $this->app->singleton(OptionValidationService::class);

        $this->app->singleton(OptionService::class, function ($app) {
            return new OptionService(
                $app->make(OptionRepositoryInterface::class),
                $app->make(OptionValidationService::class)
            );
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
