<?php

declare(strict_types=1);

namespace Pollora\Discoverer\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Pollora\Discoverer\Framework\API\PolloraDiscover;

/**
 * Service provider for bootstrapping discovered classes with automatic handling.
 *
 * This provider executes discovery and automatic handling for scouts that implement
 * HandlerScoutInterface, providing a centralized way to process discovered classes
 * without the need for separate attribute service providers.
 */
final class DiscovererBootstrapServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the discovery services and execute automatic handling.
     */
    public function register(): void
    {
        $this->bootstrapDiscoveredClasses();
    }

    /**
     * Execute discovery and automatic handling for all registered scouts.
     */
    private function bootstrapDiscoveredClasses(): void
    {
        // Get all registered scout keys
        $registeredScouts = PolloraDiscover::registered();

        foreach ($registeredScouts as $scoutKey) {
            try {
                // Execute discovery and automatic handling if the scout supports it
                PolloraDiscover::scoutAndHandle($scoutKey);
            } catch (\Throwable $e) {
                // Log error but don't break the application
                if (function_exists('error_log')) {
                    error_log("Failed to bootstrap discovered classes for scout '{$scoutKey}': ".$e->getMessage());
                }
            }
        }
    }
}
