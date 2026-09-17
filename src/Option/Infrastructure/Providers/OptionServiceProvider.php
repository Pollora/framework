<?php

declare(strict_types=1);

namespace Pollora\Option\Infrastructure\Providers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Pollora\Option\Adapter\Out\WordPress\WordPressOptionRepository;
use Pollora\Option\Application\Service\OptionService;
use Pollora\Option\Domain\Contract\OptionRepositoryInterface;
use Pollora\Option\Domain\Service\OptionValidationService;
use Pollora\Support\Facades\Option;

/**
 * Laravel service provider that bridges the `pollora/option` package into the framework.
 *
 * Wires the package's hexagonal components (repository, validation, service)
 * into the Laravel service container.
 *
 * Bindings:
 *  - {@see OptionRepositoryInterface} → {@see WordPressOptionRepository}
 *  - {@see OptionValidationService} (singleton)
 *  - {@see OptionService} (singleton, used by the Option facade)
 *
 * @see Option  The Laravel facade.
 */
final class OptionServiceProvider extends ServiceProvider
{
    /**
     * Register Option bindings into the container.
     */
    public function register(): void
    {
        $this->app->bind(
            OptionRepositoryInterface::class,
            WordPressOptionRepository::class
        );

        $this->app->singleton(OptionValidationService::class);

        $this->app->singleton(OptionService::class, fn (Application $app): OptionService => new OptionService(
            $app->make(OptionRepositoryInterface::class),
            $app->make(OptionValidationService::class)
        ));
    }
}
