<?php

declare(strict_types=1);

namespace Pollora\WpRest\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Pollora\Attributes\AttributeProcessor;
use Pollora\Discovery\Application\Services\DiscoveryManager;
use Pollora\WpRest\AbstractWpRestRoute;
use Pollora\WpRest\Infrastructure\Services\WpRestDiscovery;
use Pollora\Discovery\Domain\Contracts\DiscoveryEngineInterface;

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
        $this->registerWpRestRoutes();
        
        // Register WpRest discovery with the discovery engine
        $this->registerWpRestDiscovery();
    }

    /**
     * Register all WordPress REST API routes using the new discovery system.
     */
    protected function registerWpRestRoutes(): void
    {
        try {
            /** @var DiscoveryManager $discoveryManager */
            $discoveryManager = $this->app->make(DiscoveryManager::class);

            // Check if wp_rest_routes discovery is available
            if (!$discoveryManager->hasDiscovery('wp_rest_routes')) {
                return;
            }

            $wpRestRouteItems = $discoveryManager->getDiscoveredItems('wp_rest_routes');

            if (empty($wpRestRouteItems)) {
                return;
            }

            $processor = new AttributeProcessor($this->app);

            foreach ($wpRestRouteItems as $wpRestRouteItem) {
                $wpRestRouteClass = $wpRestRouteItem['class'] ?? null;
                if ($wpRestRouteClass) {
                    $this->registerWpRestRoute($wpRestRouteClass, $processor);
                }
            }
        } catch (\Throwable $e) {
            // Log error but don't break the application
            if (function_exists('error_log')) {
                error_log('Failed to load WordPress REST API routes: '.$e->getMessage());
            }
        }
    }

    /**
     * Register a single WordPress REST API route.
     *
     * @param  string  $wpRestRouteClass  The fully qualified class name of the REST route
     * @param  AttributeProcessor  $processor  The attribute processor
     */
    protected function registerWpRestRoute(
        string $wpRestRouteClass,
        AttributeProcessor $processor
    ): void {
        $wpRestRouteInstance = $this->app->make($wpRestRouteClass);

        if (! $wpRestRouteInstance instanceof AbstractWpRestRoute) {
            return;
        }

        // Process attributes - this will handle the registration automatically
        // through the AttributableHookTrait and the rest_api_init hook
        $processor->process($wpRestRouteInstance);
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
