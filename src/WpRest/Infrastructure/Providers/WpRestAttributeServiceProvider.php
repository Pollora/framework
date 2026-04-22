<?php

declare(strict_types=1);

namespace Pollora\WpRest\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Pollora\Discovery\Domain\Contracts\DiscoveryEngineInterface;
use Pollora\WpRest\Infrastructure\Services\WpRestDiscovery;

/**
 * Service provider for attribute-based WordPress REST API route registration.
 *
 * This provider processes REST API routes discovered by the Discoverer system
 * and registers them with WordPress following hexagonal architecture principles.
 */
class WpRestAttributeServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register WpRest Discovery
        $this->app->singleton(WpRestDiscovery::class);
    }

    /**
     * Bootstrap services.
     *
     * Processes discovered REST API routes and registers them with WordPress.
     */
    public function boot(): void
    {
        $this->registerWpRestDiscovery();
    }

    /**
     * Register WpRest discovery with the discovery engine.
     */
    private function registerWpRestDiscovery(): void
    {
        if ($this->app->bound(DiscoveryEngineInterface::class)) {
            /** @var DiscoveryEngineInterface $engine */
            $engine = $this->app->make(DiscoveryEngineInterface::class);
            $wpRestDiscovery = $this->app->make(WpRestDiscovery::class);

            $engine->addDiscovery('wp_rest_routes', $wpRestDiscovery);
        }
    }
}
