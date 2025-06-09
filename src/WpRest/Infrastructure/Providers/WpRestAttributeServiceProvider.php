<?php

declare(strict_types=1);

namespace Pollora\WpRest\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Pollora\Attributes\AttributeProcessor;
use Pollora\Discoverer\Framework\API\PolloraDiscover;
use Pollora\WpRest\AbstractWpRestRoute;

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
        // No registration needed as the main service provider handles it
    }

    /**
     * Bootstrap services.
     *
     * Processes discovered REST API routes and registers them with WordPress.
     */
    public function boot(): void
    {
        $this->registerWpRestRoutes();
    }

    /**
     * Register all WordPress REST API routes using the new discovery system.
     */
    protected function registerWpRestRoutes(): void
    {
        try {
            $wpRestRoutes = PolloraDiscover::scout('wp_rest_routes');

            if ($wpRestRoutes->isEmpty()) {
                return;
            }

            $processor = new AttributeProcessor($this->app);

            foreach ($wpRestRoutes as $wpRestRouteClass) {
                $this->registerWpRestRoute($wpRestRouteClass, $processor);
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
}
