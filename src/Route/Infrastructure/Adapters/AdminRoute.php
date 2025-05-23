<?php

declare(strict_types=1);

namespace Pollora\Route\Infrastructure\Adapters;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Class for handling WordPress admin routes.
 *
 * This class manages WordPress admin panel routes by integrating them with Laravel's routing system.
 * It provides a catch-all route for the wp-admin area and handles proper middleware configuration
 * and request binding.
 */
class AdminRoute
{
    /**
     * Create a new admin route instance.
     *
     * @param  Request  $request  The current HTTP request instance used for route binding
     * @param  Router  $router  Laravel router instance for route registration
     * @param  Repository  $config  Configuration repository
     */
    public function __construct(
        private readonly Request $request,
        private readonly Router $router,
        private readonly Repository $config
    ) {}

    /**
     * Get the WordPress admin route configuration.
     *
     * Creates and configures a catch-all route for the WordPress admin panel. This route:
     * - Matches any URL pattern under wp-admin
     * - Applies the 'admin' middleware
     * - Binds the current request
     * - Returns an empty response
     *
     * @return Route The configured WordPress admin route instance
     *
     * @example
     * // If WordPress is installed in 'cms' directory, this will match:
     * // - cms/wp-admin
     * // - cms/wp-admin/any/path
     */
    public function get(): Route
    {
        // Get WordPress installation directory from config
        $wordpressUri = trim((string) $this->config->get('app.wp.dir', 'cms'), '\/');

        // Create catch-all route for wp-admin
        $route = $this->router->any(
            "$wordpressUri/wp-admin/{any?}",
            fn (): Response => new Response
        );

        // Add admin middleware and bind request
        $route->middleware('admin');
        $route->bind($this->request);

        return $route;
    }
}
