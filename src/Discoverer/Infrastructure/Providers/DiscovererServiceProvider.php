<?php

declare(strict_types=1);

namespace Pollora\Discoverer\Infrastructure\Providers;

use Illuminate\Container\Container;
use Illuminate\Support\ServiceProvider;
use Pollora\Discoverer\Domain\Contracts\ScoutRegistryInterface;
use Pollora\Discoverer\Framework\API\PolloraDiscover;
use Pollora\Discoverer\Infrastructure\Registry\ScoutRegistry;
use Pollora\Discoverer\Scouts\AttributableClassesScout;
use Pollora\Discoverer\Scouts\HookClassesScout;
use Pollora\Discoverer\Scouts\PostTypeClassesScout;
use Pollora\Discoverer\Scouts\TaxonomyClassesScout;
use Pollora\Discoverer\Scouts\ThemeServiceProviderScout;
use Pollora\Discoverer\Scouts\WpRestRoutesScout;

/**
 * Service provider for the simplified Pollora discovery system.
 *
 * This provider registers the core discovery services and scouts following
 * the new simplified architecture. It provides a clean registry-based approach
 * for scout management and automatic discovery of framework components.
 */
final class DiscovererServiceProvider extends ServiceProvider
{
    /**
     * Core scouts provided by the framework.
     *
     * @var array<string, string> Scout key => Scout class mappings
     */
    private array $coreScouts = [
        'attributable' => AttributableClassesScout::class,
        'hooks' => HookClassesScout::class,
        'post_types' => PostTypeClassesScout::class,
        'taxonomies' => TaxonomyClassesScout::class,
        'theme_providers' => ThemeServiceProviderScout::class,
        'wp_rest_routes' => WpRestRoutesScout::class,
    ];

    /**
     * Register the discovery services in the container.
     */
    public function register(): void
    {
        $this->registerScoutRegistry();
        $this->registerCoreScouts();
    }

    /**
     * Bootstrap the discovery services.
     */
    public function boot(): void
    {
        $this->bootCoreScouts();
    }

    /**
     * Register the scout registry as a singleton.
     */
    private function registerScoutRegistry(): void
    {
        // @TODO use dependency injection for scout registries
        $this->app->singleton(ScoutRegistryInterface::class, function (Container $app): ScoutRegistry {
            return new ScoutRegistry($app);
        });
    }

    /**
     * Register core scout classes in the container.
     */
    private function registerCoreScouts(): void
    {
        // @TODO use dependency injection for scout classes
        foreach ($this->coreScouts as $scoutClass) {
            $this->app->singleton($scoutClass, function (Container $app) use ($scoutClass): object {
                return new $scoutClass($app);
            });
        }
    }

    /**
     * Register core scouts with the discovery system.
     */
    private function bootCoreScouts(): void
    {
        foreach ($this->coreScouts as $key => $scoutClass) {
            PolloraDiscover::register($key, $scoutClass);
        }
    }

    /**
     * Get the services provided by this provider.
     *
     * @return array<string> Array of provided services
     */
    public function provides(): array
    {
        return [
            ScoutRegistryInterface::class,
            ...array_values($this->coreScouts),
        ];
    }
}
