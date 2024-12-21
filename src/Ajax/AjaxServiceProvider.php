<?php

declare(strict_types=1);

namespace Pollora\Ajax;

use Illuminate\Support\ServiceProvider;

/**
 * Service provider for WordPress AJAX handling.
 *
 * This provider registers the AjaxFactory as a singleton in the service container,
 * making it available throughout the application for handling AJAX requests
 * in a Laravel-like way. It integrates WordPress's AJAX system with Laravel's
 * dependency injection and response handling capabilities.
 *
 * @extends ServiceProvider
 */
class AjaxServiceProvider extends ServiceProvider
{
    /**
     * Register WordPress AJAX services.
     *
     * Binds the AjaxFactory as a singleton in the service container under the
     * 'wp.ajax' alias, providing a centralized way to handle AJAX requests
     * with Laravel's features.
     */
    public function register(): void
    {
        $this->app->singleton('wp.ajax', fn ($app): \Pollora\Ajax\AjaxFactory => new AjaxFactory);
    }
}
