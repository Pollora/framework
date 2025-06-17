<?php

declare(strict_types=1);

namespace Pollora\PostType\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Service provider for attribute-based post type registration.
 *
 * @deprecated This provider is now handled automatically by the Discoverer system.
 * The PostTypeClassesScout now implements HandlerScoutInterface and processes
 * discovered post types automatically. This provider is kept for backward compatibility
 * but can be removed in future versions.
 *
 * @see \Pollora\Discoverer\Scouts\PostTypeClassesScout
 */
class PostTypeAttributeServiceProvider extends ServiceProvider
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
     * @deprecated Post types are now processed automatically by the Discoverer system
     */
    public function boot(): void
    {
        // Post types are now automatically discovered and processed
        // by the PostTypeClassesScout through the Discoverer system.
        // No manual processing needed here.
    }
}
