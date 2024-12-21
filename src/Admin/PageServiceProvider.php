<?php

declare(strict_types=1);

namespace Pollora\Admin;

use Illuminate\Support\ServiceProvider;

/**
 * Service provider for WordPress admin page management.
 *
 * This provider registers the PageFactory as a singleton in the service container,
 * making it available throughout the application for creating and managing
 * WordPress admin pages in a Laravel-like way.
 *
 * @extends ServiceProvider
 */
class PageServiceProvider extends ServiceProvider
{
    /**
     * Register WordPress admin page services.
     *
     * Binds the PageFactory as a singleton in the service container under the
     * 'wp.admin.page' alias, configured with a new Page instance.
     */
    public function register(): void
    {
        $this->app->singleton(
            'wp.admin.page',
            fn ($app): \Pollora\Admin\PageFactory => new PageFactory(new Page($app))
        );
    }
}
