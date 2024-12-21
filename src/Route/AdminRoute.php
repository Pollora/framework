<?php

declare(strict_types=1);

namespace Pollora\Route;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Router as IlluminateRouter;

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
     * @param Request $request The current HTTP request instance used for route binding
     * @param IlluminateRouter $router Laravel router instance for route registration
     */
    public function __construct(
        private readonly Request $request,
        private readonly IlluminateRouter $router
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
     * @return \Illuminate\Routing\Route The configured WordPress admin route instance
     *
     * @example
     * // If WordPress is installed in 'cms' directory, this will match:
     * // - cms/wp-admin
     * // - cms/wp-admin/any/path
     */
    public function get(): \Illuminate\Routing\Route
    {
        // Get WordPress installation directory from config
        $wordpressUri = trim((string) config('app.wp.dir', 'cms'), '\/');
        
        // Create catch-all route for wp-admin
        $route = $this->router->any(
            "$wordpressUri/wp-admin/{any?}", 
            fn (): \Illuminate\Http\Response => new Response
        );

        // Add admin middleware and bind request
        $route->middleware('admin');
        $route->bind($this->request);

        return $route;
    }
}
