<?php

declare(strict_types=1);

namespace Pollora\Route;

use Illuminate\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Routing\Router as IlluminateRouter;
use Pollora\Route\Bindings\NullableWpPost;
use Pollora\Route\Route;

/**
 * Extended Router that provides WordPress-specific routing functionality.
 *
 * This class extends Laravel's Router to handle WordPress-specific routing needs,
 * including admin routes, WordPress conditions, and post bindings.
 */
class Router extends IlluminateRouter
{
    /**
     * Array of WordPress routing conditions.
     *
     * @var array<string, mixed> Registered WordPress routing conditions
     */
    protected array $conditions = [];

    /**
     * Create a new Router instance.
     *
     * @param  Dispatcher  $events  Event dispatcher instance
     * @param  Container|null  $container  Service container instance
     */
    public function __construct(Dispatcher $events, ?Container $container = null)
    {
        parent::__construct($events, $container);
        $this->routes = new RouteCollection;
    }

    /**
     * Create a new Route instance.
     *
     * @param  array<string>  $methods  HTTP methods
     * @param  string  $uri  URI pattern
     * @param  mixed  $action  Route action
     * @return \Pollora\Route\Route New route instance with WordPress conditions
     */
    public function newRoute($methods, $uri, $action): \Pollora\Route\Route
    {
        $this->setConditionsIfEmpty();

        return (new Route($methods, $uri, $action))
            ->setRouter($this)
            ->setContainer($this->container)
            ->setConditions($this->conditions);
    }

    /**
     * Find the route matching a given request.
     *
     * Handles special case for WordPress admin requests by creating
     * a dedicated admin route.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Routing\Route
     */
    protected function findRoute($request)
    {
        // Handle WordPress admin requests
        if ($this->isWordPressAdminRequest()) {
            return $this->createAdminRoute($request);
        }

        try {
            // First try to find a standard Laravel route
            $laravelRoute = parent::findRoute($request);
            
            // If it's a WordPress route, check if there's a more specific route
            if ($laravelRoute instanceof Route && $laravelRoute->isWordPressRoute()) {
                // Get all WordPress routes
                $wpRoutes = [];
                foreach ($this->routes->getRoutes() as $route) {
                    if ($route instanceof Route && $route->isWordPressRoute()) {
                        $wpRoutes[] = $route;
                    }
                }
                
                // Check which WordPress routes match the current request
                $matchingRoutes = [];
                foreach ($wpRoutes as $route) {
                    $condition = $route->getCondition();
                    if (function_exists($condition)) {
                        // Directly check if the WordPress condition is satisfied
                        $params = $route->getConditionParameters();
                        if (call_user_func_array($condition, $params)) {
                            $matchingRoutes[$condition] = $route;
                        }
                    }
                }
                
                // If routes match, find the most specific one
                if (!empty($matchingRoutes)) {
                    // Get the WordPress template hierarchy order
                    $hierarchyOrder = \Pollora\Theme\TemplateHierarchy::getHierarchyOrder();
                    
                    // Go through the hierarchy order to find the most specific route
                    foreach ($hierarchyOrder as $condition) {
                        if (isset($matchingRoutes[$condition])) {
                            $route = $matchingRoutes[$condition];
                            
                            // Initialize parameters if needed
                            if (!isset($route->parameters)) {
                                $route->parameters = [];
                            }
                            
                            // Add WordPress bindings
                            global $post, $wp_query;
                            $route->parameters['post'] = $post ?? (new NullableWpPost)->toWpPost();
                            $route->parameters['wp_query'] = $wp_query;
                            
                            return $route;
                        }
                    }
                }
            }
            
            return $laravelRoute;
        } catch (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e) {
            // If no route is found, let the FrontendController handle the request
            return null;
        }
    }

    /**
     * Set WordPress routing conditions.
     *
     * Merges provided conditions with those from configuration.
     *
     * @param  array<string, mixed>  $conditions  Additional conditions to set
     */
    public function setConditions(array $conditions = []): void
    {
        $this->conditions = array_merge(
            $this->container->make('config')->get('wordpress.conditions', []),
            $conditions
        );
    }

    /**
     * Add WordPress-specific bindings to a route.
     *
     * Binds current WordPress post and query objects to the route parameters.
     * Only applies to routes created with Route::wordpress().
     *
     * @param  Route  $route  Route to add bindings to
     * @return Route Route with WordPress bindings
     */
    public function addWordPressBindings($route)
    {
        // Don't add bindings if it's not a WordPress route
        if (! ($route instanceof Route) || ! $route->isWordPressRoute()) {
            return $route;
        }

        global $post, $wp_query;

        // Initialize parameters if needed
        if (!isset($route->parameters)) {
            $route->parameters = [];
        }

        // Directly add WordPress bindings to parameters
        $route->parameters['post'] = $post ?? (new NullableWpPost)->toWpPost();
        $route->parameters['wp_query'] = $wp_query;

        return $route;
    }

    /**
     * Ensure conditions are set if they haven't been initialized.
     */
    private function setConditionsIfEmpty(): void
    {
        if ($this->conditions === []) {
            $this->setConditions();
        }
    }

    /**
     * Determine if the current request is for the WordPress admin area.
     *
     * @return bool True if request is for wp-admin, false otherwise
     */
    private function isWordPressAdminRequest(): bool
    {
        $app = $this->container['app'] ?? null;

        return $app && method_exists($app, 'isWordPressAdmin') && $app->isWordPressAdmin();
    }

    /**
     * Create a new WordPress admin route.
     *
     * Sets up a route specifically for handling WordPress admin requests
     * and registers it in the container.
     *
     * @param  \Illuminate\Http\Request  $request  Current request instance
     * @return \Illuminate\Routing\Route Configured admin route
     */
    private function createAdminRoute($request): \Illuminate\Routing\Route
    {
        $route = (new AdminRoute($request, $this))->get();
        $this->current = $route;
        $this->container->instance(Route::class, $route);

        return $route;
    }
}
