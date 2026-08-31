<?php

declare(strict_types=1);

namespace Pollora\Ability\Infrastructure\Providers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Pollora\Abilities\Adapter\Out\WordPress\WordPressAbilityCategoryRegistrar;
use Pollora\Abilities\Adapter\Out\WordPress\WordPressAbilityRegistrar;
use Pollora\Abilities\Application\Service\RegisterAbilityService;
use Pollora\Abilities\Factory\AbilityFactory;
use Pollora\Abilities\Port\Out\AbilityCategoryRegistrarPort;
use Pollora\Abilities\Port\Out\AbilityRegistrarPort;
use Pollora\Ability\Infrastructure\Services\AbilityDiscovery;
use Pollora\Attributes\Ability;
use Pollora\Hook\Domain\Contract\Action;

/**
 * Laravel service provider that bridges the `pollora/abilities` package into the framework.
 *
 * Wires the package's hexagonal components (ports, service, factory) into the
 * service container, registers the `#[Ability]` attribute discovery, and flushes
 * the declaration queues on the two WordPress hooks that accept them.
 *
 * Bindings:
 *  - {@see AbilityRegistrarPort} → {@see WordPressAbilityRegistrar} (singleton)
 *  - {@see AbilityCategoryRegistrarPort} → {@see WordPressAbilityCategoryRegistrar} (singleton)
 *  - {@see RegisterAbilityService} (singleton)
 *  - `wp.abilities` → {@see AbilityFactory} (singleton, used by the Ability facade)
 *  - {@see AbilityDiscovery} (singleton, picked up by the discovery engine)
 *
 * @see \Pollora\Support\Facades\Ability  The Laravel facade resolved via `wp.abilities`.
 * @see Ability                           The PHP attribute discovered by this provider.
 */
class AbilityServiceProvider extends ServiceProvider
{
    /**
     * Register ability bindings into the container.
     */
    public function register(): void
    {
        $this->app->singleton(AbilityRegistrarPort::class, WordPressAbilityRegistrar::class);
        $this->app->singleton(AbilityCategoryRegistrarPort::class, WordPressAbilityCategoryRegistrar::class);

        $this->app->singleton(RegisterAbilityService::class, fn (Application $app): RegisterAbilityService => new RegisterAbilityService(
            $app->make(AbilityRegistrarPort::class),
            $app->make(AbilityCategoryRegistrarPort::class),
        ));

        $this->app->singleton('wp.abilities', fn (Application $app): AbilityFactory => new AbilityFactory(
            $app->make(RegisterAbilityService::class)
        ));

        $this->app->singleton(AbilityDiscovery::class, fn (Application $app): AbilityDiscovery => new AbilityDiscovery(
            $app->make('wp.abilities')
        ));
    }

    /**
     * Publish everything declared so far, on the hooks WordPress accepts it on.
     *
     * Categories go first and on their own hook: WordPress initialises the
     * category registry earlier, and an ability naming a category that does not
     * exist yet fails to register.
     */
    public function boot(): void
    {
        $action = $this->app->get(Action::class);
        $service = $this->app->make(RegisterAbilityService::class);

        $action->add('wp_abilities_api_categories_init', static function () use ($service): void {
            $service->flushCategories();
        });

        $action->add('wp_abilities_api_init', static function () use ($service): void {
            $service->flushAbilities();
        });
    }
}
