<?php

declare(strict_types=1);

namespace Pollora\Route;

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
                        if (isset($matchingRoutes[$condition])) {
                            $route = $matchingRoutes[$condition];

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
     * Create a special route for WordPress special request types.
     *
     * This route will delegate to WordPress's built-in handlers for
     * robots.txt, favicon, feeds, and trackbacks.
     *
     * @param  Request  $request  Current request instance
     * @return \Illuminate\Routing\Route Special WordPress route
     */
    private function createSpecialWordPressRoute(Request $request): \Illuminate\Routing\Route
    {
        // Determine which special handler to use
        $handler = function (): void {
            if (function_exists('is_robots') && is_robots()) {
                do_action('do_robots');
                exit;
            }
            if (function_exists('is_favicon') && is_favicon()) {
                do_action('do_favicon');
                exit;
            }
            if (function_exists('is_feed') && is_feed()) {
                do_feed();
                exit;
            }
            if (function_exists('is_trackback') && is_trackback()) {
                require_once ABSPATH.'wp-trackback.php';
                exit;
            }

            // Fallback to 404 if none of the special handlers match
            abort(404);
        };

        // Create a route with the special handler
        $route = $this->newRoute(['GET', 'HEAD'], $request->path(), $handler);
        $route->setIsWordPressRoute(true);

        // Set the current route
        $this->current = $route;
        $this->container->instance(Route::class, $route);

        return $route;
    }

    /**
     * Create a fallback route for requests that don't match any defined routes.
     *
     * This route will delegate to the FrontendController to handle the request
     * using WordPress's template hierarchy.
     *
     * @param  Request  $request  Current request instance
     * @return \Illuminate\Routing\Route Fallback route
     */
    private function createFallbackRoute(Request $request): \Illuminate\Routing\Route
    {
        // Create a route that delegates to the FrontendController
        $route = $this->newRoute(['GET', 'HEAD'], $request->path(), [
            'uses' => 'Pollora\Http\Controllers\FrontendController@handle',
        ]);

        // Set the current route
        $this->current = $route;
        $this->container->instance(Route::class, $route);

        return $route;
    }

    /**
     * Set WordPress routing conditions.
     *
     * Merges provided conditions with those from configuration.
     * Plugin conditions are added with higher priority than native conditions.
     *
     * @param  array<string, mixed>  $conditions  Additional conditions to set
     */
    public function setConditions(array $conditions = []): void
    {
        $config = $this->container->make('config');

        // Get plugin conditions first (higher priority)
        $pluginConditions = [];
        $pluginConditionsConfig = $config->get('wordpress.plugin_conditions', []);

        foreach ($pluginConditionsConfig as $pluginName => $pluginConditionGroup) {
            $pluginConditions = array_merge($pluginConditions, $pluginConditionGroup);
        }

        // Then get native WordPress conditions
        $nativeConditions = $config->get('wordpress.conditions', []);

        // Merge in order of priority: custom conditions, plugin conditions, native conditions
        $this->conditions = array_merge(
            $conditions,
            $pluginConditions,
            $nativeConditions
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
    public function addWordPressBindings(Route $route): Route
    {
        // Don't add bindings if it's not a WordPress route
        if (! ($route instanceof Route) || ! $route->isWordPressRoute()) {
            return $route;
        }

        global $post, $wp_query;

        // Initialize parameters if needed
        if ($route->parameters === null) {
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
     * @TODO implement exception handling for WordPress Admin specific routes
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
     * @param  Request  $request  Current request instance
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
