<?php

declare(strict_types=1);

namespace Pollora\Discoverer;

use Illuminate\Support\ServiceProvider;
use Pollora\Discoverer\Contracts\Discoverable;
use Pollora\Discoverer\Contracts\DiscoveryRegistry;
use Pollora\Discoverer\Scouts\AbstractScout;
use Pollora\Discoverer\Scouts\AttributeScout;
use Pollora\Discoverer\Scouts\HookScout;
use Pollora\Discoverer\Scouts\PostTypeScout;
use Pollora\Discoverer\Scouts\RestScout;
use Pollora\Discoverer\Scouts\TaxonomyScout;

/**
 * Service provider for class auto-discovery functionality.
 */
class DiscovererServiceProvider extends ServiceProvider
{
    /**
     * Default scouts that should always be registered.
     *
     * @var array<class-string>
     */
    protected array $scouts = [
        HookScout::class,
        AttributeScout::class,
        PostTypeScout::class,
        TaxonomyScout::class,
        RestScout::class,
    ];

    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(DiscoveryRegistry::class, Registry::class);

        $this->app->singleton(Discoverer::class, function ($app) {
            return new Discoverer(
                $app->make(DiscoveryRegistry::class)
            );
        });

        // Register default scouts
        foreach ($this->scouts as $scoutClass) {
            $this->app->singleton($scoutClass);
        }
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->registerDiscoveryScouts();
    }

    /**
     * Register all structure discovery scouts.
     */
    protected function registerDiscoveryScouts(): void
    {
        // Get registry
        $registry = $this->app->make(DiscoveryRegistry::class);

        $allScouts = $this->scouts;

        // Register each scout with the registry
        foreach ($allScouts as $scoutClass) {
            try {
                $scoutInstance = $this->app->make($scoutClass);

                if ($scoutInstance instanceof AbstractScout) {
                    // Register the discovered classes with the registry
                    $discoveredClasses = $scoutInstance->get();

                    $type = $scoutInstance->getType();

                    // Enregistrer dans le registry
                    foreach ($discoveredClasses as $discoveredClass) {
                        if (class_exists($discoveredClass)) {
                            $registry->register($discoveredClass, $type);
                            $this->registerDiscoveredClass($discoveredClass);
                        }
                    }
                }
            } catch (\Exception $e) {
                $this->app->make('log')->error(
                    "Error processing scout {$scoutClass}: " . $e->getMessage()
                );
            }
        }
    }

    /**
     * Register a discovered class with the application.
     *
     * @param string $discoveredClass Fully qualified class name
     */
    protected function registerDiscoveredClass(string $discoveredClass): void
    {
        if (!class_exists($discoveredClass)) {
            return;
        }

        try {
            $instance = $this->app->make($discoveredClass);
            // If the discovered class implements Discoverable, register it
            if ($instance instanceof Discoverable) {
                $instance->register();
            }
        } catch (\Exception $e) {
            $this->app->make('log')->error(
                "Error registering discovered class {$discoveredClass}: " . $e->getMessage()
            );
        }
    }
}
