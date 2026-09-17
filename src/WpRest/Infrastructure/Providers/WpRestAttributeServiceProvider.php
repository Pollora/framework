<?php

declare(strict_types=1);

namespace Pollora\WpRest\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
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
        $this->app->singleton(WpRestDiscovery::class);
    }
}
