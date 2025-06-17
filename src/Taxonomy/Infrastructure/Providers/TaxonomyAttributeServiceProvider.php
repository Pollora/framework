<?php

declare(strict_types=1);

namespace Pollora\Taxonomy\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Service provider for attribute-based taxonomy registration.
 *
 * @deprecated This provider is now handled automatically by the Discoverer system.
 * The TaxonomyClassesScout now implements HandlerScoutInterface and processes
 * discovered taxonomies automatically. This provider is kept for backward compatibility
 * but can be removed in future versions.
 *
 * @see \Pollora\Discoverer\Scouts\TaxonomyClassesScout
 */
class TaxonomyAttributeServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // No registration needed - handled by the Discoverer system
    }

    /**
     * Bootstrap services.
     *
     * @deprecated Taxonomies are now processed automatically by the Discoverer system
     */
    public function boot(): void
    {
        // Taxonomies are now automatically discovered and processed
        // by the TaxonomyClassesScout through the Discoverer system.
        // No manual processing needed here.
    }
}
