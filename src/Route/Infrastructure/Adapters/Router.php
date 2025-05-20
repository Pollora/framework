<?php

declare(strict_types=1);

namespace Pollora\Route\Infrastructure\Adapters;

use Illuminate\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Routing\Router as IlluminateRouter;
use Pollora\Route\Bindings\NullableWpPost;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
        
        // Ensure we properly set the container for route resolution
        if ($container) {
            $this->container = $container;
        }
    }

    /**
     * Create a new Route instance.
     *
     * @param  array<string>  $methods  HTTP methods
     * @param  string  $uri  URI pattern
     * @param  mixed  $action  Route action
     * @return Route New route instance with WordPress conditions
     */
    public function newRoute($methods, $uri, $action): Route
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
     * a dedicated admin route. Also handles special WordPress request types
     * like robots.txt, favicon, feeds, and trackbacks.
     *
     * @param  Request  $request
     * @return \Illuminate\Routing\Route
     */
    protected function findRoute($request)
    {
        // Handle WordPress admin requests
        if ($this->isWordPressAdminRequest()) {
            return $this->createAdminRoute($request);
        }

        // Handle special WordPress request types (robots, favicon, feed, trackback)
        if ($this->isSpecialWordPressRequest()) {
            // First check if there's an explicit route defined for this special request
            $specialRoute = $this->findSpecialWordPressRoute();
            if ($specialRoute instanceof Route) {
                return $specialRoute;
            }

            // If no explicit route is defined, create a special route that will
            // delegate to WordPress's built-in handlers
            return $this->createSpecialWordPressRoute($request);
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

                // First, sort the WordPress routes by priority based on their condition parameters
                // This ensures more specific routes are checked first
                usort($wpRoutes, function ($a, $b) {
                    // Routes with parameters should come first
                    $aHasParams = ! empty($a->getConditionParameters());
                    $bHasParams = ! empty($b->getConditionParameters());

                    if ($aHasParams && ! $bHasParams) {
                        return -1;
                    }

                    if (! $aHasParams && $bHasParams) {
                        return 1;
                    }

                    // If both have or don't have parameters, maintain original order
                    return 0;
                });

                // Then check each route
                foreach ($wpRoutes as $route) {
                    $condition = $route->getCondition();
                    if (function_exists($condition)) {
                        // Directly check if the WordPress condition is satisfied
                        $params = $route->getConditionParameters();
                        if (call_user_func_array($condition, $params)) {
                            // Generate a unique key for this route that includes both condition and parameters
                            $uniqueKey = $condition;
                            if (! empty($params)) {
                                $uniqueKey .= ':'.serialize($params);
                            }
                            $matchingRoutes[$uniqueKey] = $route;
                        }
                    }
                }

                // If routes match, find the most specific one
                if ($matchingRoutes !== []) {
                    // Get the WordPress conditions and plugin conditions from the config
                    $config = $this->container->make('config');

                    // Get plugin conditions (higher priority)
                    $pluginConditionsConfig = $config->get('wordpress.plugin_conditions', []);
                    $pluginConditions = [];

                    foreach ($pluginConditionsConfig as $pluginName => $pluginConditionGroup) {
                        $pluginConditions = array_merge($pluginConditions, array_keys($pluginConditionGroup));
                    }

                    // Get native WordPress conditions (lower priority)
                    $wordpressConditions = $config->get('wordpress.conditions', []);

                    // Create hierarchyOrder with plugin conditions first (more specific)
                    // Then add native WordPress conditions
                    $hierarchyOrder = array_merge(
                        $pluginConditions,
                        array_keys($wordpressConditions)
                    );

                    // Add fallback condition
                    $hierarchyOrder[] = '__return_true';

                    // Go through the hierarchy order to find the most specific route
                    foreach ($hierarchyOrder as $condition) {
                        // Find any route with this condition (regardless of parameters)
                        $matchedRoute = null;

                        foreach ($matchingRoutes as $key => $route) {
                            if (strpos($key, $condition) === 0) {
                                $matchedRoute = $route;
                                break;
                            }
                        }

                        if ($matchedRoute) {
                            // Initialize parameters if needed
                            if ($matchedRoute->parameters === null) {
                                $matchedRoute->parameters = [];
                            }

                            // Add WordPress bindings
                            global $post, $wp_query;
                            $matchedRoute->parameters['post'] = $post ?? (new NullableWpPost)->toWpPost();
                            $matchedRoute->parameters['wp_query'] = $wp_query;

                            return $matchedRoute;
                        }
                    }
                }
            }

            return $laravelRoute;
        } catch (NotFoundHttpException) {
            // If no route is found, create a fallback route that will
            // delegate to the FrontendController
            return $this->createFallbackRoute($request);
        }
    }

    /**
     * Check if the current request is a special WordPress request type.
     *
     * Special request types include robots.txt, favicon, feeds, and trackbacks.
     *
     * @return bool True if this is a special WordPress request, false otherwise
     */
    private function isSpecialWordPressRequest(): bool
    {
        return (function_exists('is_robots') && is_robots())
            || (function_exists('is_favicon') && is_favicon())
            || (function_exists('is_feed') && is_feed())
            || (function_exists('is_trackback') && is_trackback());
    }

    /**
     * Find a route explicitly defined for special WordPress request types.
     *
     * @return Route|null The matching route or null if none found
     */
    private function findSpecialWordPressRoute(): ?Route
    {
        $specialCondition = null;

        if (function_exists('is_robots') && is_robots()) {
            $specialCondition = 'is_robots';
        } elseif (function_exists('is_favicon') && is_favicon()) {
            $specialCondition = 'is_favicon';
        } elseif (function_exists('is_feed') && is_feed()) {
            $specialCondition = 'is_feed';
        } elseif (function_exists('is_trackback') && is_trackback()) {
            $specialCondition = 'is_trackback';
        }

        if ($specialCondition === null) {
            return null;
        }

        // Look for a route with this specific condition
        foreach ($this->routes->getRoutes() as $route) {
            if ($route instanceof Route
                && $route->isWordPressRoute()
                && $route->getCondition() === $specialCondition) {
                return $route;
            }
        }

        return null;
    }

    /**
     * Create a special route for WordPress built-in request types.
     *
     * @param  Request  $request  The current request
     * @return Route A route that delegates to WordPress's handling
     */
    private function createSpecialWordPressRoute(Request $request): Route
    {
        $methods = ['GET', 'HEAD'];
        $action = [
            'middleware' => [
                'web',
            ],
            'uses' => fn () => new \Illuminate\Http\Response(),
        ];

        // Create a new route with a unique name based on the type of special request
        $specialType = 'unknown';
        if (function_exists('is_robots') && is_robots()) {
            $specialType = 'robots';
        } elseif (function_exists('is_favicon') && is_favicon()) {
            $specialType = 'favicon';
        } elseif (function_exists('is_feed') && is_feed()) {
            $specialType = 'feed';
        } elseif (function_exists('is_trackback') && is_trackback()) {
            $specialType = 'trackback';
        }

        // Create a route for this special request
        $route = new Route($methods, "special-wordpress-{$specialType}", $action);
        $route->bind($request);

        return $route;
    }

    /**
     * Create a fallback route for WordPress template handling.
     *
     * @param  Request  $request  The current request
     * @return Route A route that delegates to the FrontendController
     */
    private function createFallbackRoute(Request $request): Route
    {
        $methods = ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE'];
        $action = [
            'uses' => 'Pollora\Http\Controllers\FrontendController@handle',
            'controller' => 'Pollora\Http\Controllers\FrontendController@handle',
            'namespace' => null,
            'prefix' => null,
        ];

        // Create a new fallback route
        $route = new Route($methods, '{fallbackPlaceholder}', $action);
        $route->where('fallbackPlaceholder', '.*');
        $route->bind($request);

        return $route;
    }

    /**
     * Set WordPress conditions for routes.
     *
     * @param  array<string, mixed>  $conditions  Mapping of condition signatures to routes
     * @return void
     */
    public function setConditions(array $conditions = []): void
    {
        $this->conditions = $conditions;
    }

    /**
     * Add WordPress bindings to a route.
     *
     * @param  Route  $route  The route to add bindings to
     * @return Route Returns the modified route
     */
    public function addWordPressBindings(Route $route): Route
    {
        // Initialize parameters if needed
        if ($route->parameters === null) {
            $route->parameters = [];
        }

        // Add WordPress bindings
        global $post, $wp_query;
        $route->parameters['post'] = $post ?? (new NullableWpPost)->toWpPost();
        $route->parameters['wp_query'] = $wp_query;

        return $route;
    }

    /**
     * Initialize WordPress conditions from config if not already set.
     *
     * @return void
     */
    private function setConditionsIfEmpty(): void
    {
        if ($this->conditions === []) {
            $config = $this->container->make('config');
            $this->conditions = $config->get('wordpress.conditions', []);
        }
    }

    /**
     * Check if the current request is for the WordPress admin area.
     *
     * @return bool True if this is an admin request, false otherwise
     */
    private function isWordPressAdminRequest(): bool
    {
        return function_exists('is_admin') && is_admin();
    }

    /**
     * Create an admin route for WordPress admin panel handling.
     *
     * @param  Request  $request  The current request
     * @return Route A route for WordPress admin requests
     */
    private function createAdminRoute($request): Route
    {
        $config = $this->container->make('config');
        $adminRoute = new AdminRoute($request, $this, $config);
        return $adminRoute->get();
    }
} 