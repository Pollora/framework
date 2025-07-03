<?php

declare(strict_types=1);

use Pollora\Modules\Domain\Contracts\OnDemandDiscoveryInterface;

if (!function_exists('pollora_discover_module')) {
    /**
     * Discover structures in a module directory (generic).
     *
     * @param string $modulePath The module directory path
     * @param callable|null $processor Optional processor function (unused in new system)
     * @return array<string, array> Results grouped by discovery type
     */
    function pollora_discover_module(string $modulePath, ?callable $processor = null): array
    {
        if (!function_exists('app') || !app()->bound(OnDemandDiscoveryInterface::class)) {
            return [];
        }

        try {
            $discoveryService = app(OnDemandDiscoveryInterface::class);
            $discoveryService->discoverModule($modulePath);
            return $discoveryService->discoverAllInPath($modulePath);
        } catch (Exception $e) {
            if (function_exists('error_log')) {
                error_log('pollora_discover_module error: ' . $e->getMessage());
            }
            return [];
        }
    }
}

if (!function_exists('pollora_discover_theme')) {
    /**
     * Discover structures in a theme directory.
     *
     * @param string $themePath The theme directory path
     * @param callable|null $processor Optional processor function (unused in new system)
     * @return array<string, array> Results grouped by discovery type
     */
    function pollora_discover_theme(string $themePath, ?callable $processor = null): array
    {
        if (!function_exists('app') || !app()->bound(OnDemandDiscoveryInterface::class)) {
            return [];
        }

        try {
            $discoveryService = app(OnDemandDiscoveryInterface::class);
            $discoveryService->discoverTheme($themePath);
            return $discoveryService->discoverAllInPath($themePath);
        } catch (Exception $e) {
            if (function_exists('error_log')) {
                error_log('pollora_discover_theme error: ' . $e->getMessage());
            }
            return [];
        }
    }
}

if (!function_exists('pollora_discover_plugin')) {
    /**
     * Discover structures in a plugin directory.
     *
     * @param string $pluginPath The plugin directory path
     * @param callable|null $processor Optional processor function (unused in new system)
     * @return array<string, array> Results grouped by discovery type
     */
    function pollora_discover_plugin(string $pluginPath, ?callable $processor = null): array
    {
        if (!function_exists('app') || !app()->bound(OnDemandDiscoveryInterface::class)) {
            return [];
        }

        try {
            $discoveryService = app(OnDemandDiscoveryInterface::class);
            $discoveryService->discoverPlugin($pluginPath);
            return $discoveryService->discoverAllInPath($pluginPath);
        } catch (Exception $e) {
            if (function_exists('error_log')) {
                error_log('pollora_discover_plugin error: ' . $e->getMessage());
            }
            return [];
        }
    }
}

if (!function_exists('pollora_discover_in_path')) {
    /**
     * Discover structures in a specific path using a scout class.
     *
     * @param string $path The path to explore
     * @param string $scoutClass The scout class to use (legacy support)
     * @return array Array of discovered structures
     */
    function pollora_discover_in_path(string $path, string $scoutClass): array
    {
        if (!function_exists('app') || !app()->bound(OnDemandDiscoveryInterface::class)) {
            return [];
        }

        try {
            $discoveryService = app(OnDemandDiscoveryInterface::class);
            $discoveryService->discoverInPath($path, $scoutClass);
            return $discoveryService->discoverAllInPath($path);
        } catch (Exception $e) {
            if (function_exists('error_log')) {
                error_log('pollora_discover_in_path error: ' . $e->getMessage());
            }
            return [];
        }
    }
}

if (!function_exists('pollora_discover_all_in_path')) {
    /**
     * Discover all structure types in a given path.
     *
     * @param string $path The path to explore
     * @return array<string, array> Results grouped by discovery type
     */
    function pollora_discover_all_in_path(string $path): array
    {
        if (!function_exists('app') || !app()->bound(OnDemandDiscoveryInterface::class)) {
            return [];
        }

        try {
            $discoveryService = app(OnDemandDiscoveryInterface::class);
            return $discoveryService->discoverAllInPath($path);
        } catch (Exception $e) {
            if (function_exists('error_log')) {
                error_log('pollora_discover_all_in_path error: ' . $e->getMessage());
            }
            return [];
        }
    }
}

if (!function_exists('pollora_debug_route_registration')) {
    /**
     * Debug function to check route registration order and status.
     *
     * This function helps troubleshoot route registration issues between
     * modules and the WordPress fallback route.
     *
     * @return array Debug information about route registration
     */
    function pollora_debug_route_registration(): array
    {
        $debug = [
            'routes_count' => 0,
            'fallback_registered' => false,
            'modules_event_fired' => false,
            'routes' => [],
            'fallback_route' => null
        ];

        if (!function_exists('app')) {
            $debug['error'] = 'Laravel app() function not available';
            return $debug;
        }

        try {
            /** @var \Illuminate\Routing\Router $router */
            $router = app('router');
            $routes = $router->getRoutes();
            
            $debug['routes_count'] = $routes->count();
            $debug['fallback_registered'] = app()->bound('route.fallback.registered');

            // Analyze routes
            foreach ($routes as $route) {
                $routeInfo = [
                    'uri' => $route->uri(),
                    'methods' => $route->methods(),
                    'name' => $route->getName(),
                    'action' => $route->getActionName(),
                    'middleware' => $route->middleware(),
                ];

                // Check if this is the fallback route
                if ($route->uri() === '{any}') {
                    $debug['fallback_route'] = $routeInfo;
                } else {
                    $debug['routes'][] = $routeInfo;
                }
            }

        } catch (\Exception $e) {
            $debug['error'] = $e->getMessage();
        }

        return $debug;
    }
}

if (!function_exists('pollora_list_module_routes')) {
    /**
     * List all routes registered by modules.
     *
     * @return array Array of module routes
     */
    function pollora_list_module_routes(): array
    {
        $moduleRoutes = [];

        if (!function_exists('app')) {
            return $moduleRoutes;
        }

        try {
            /** @var \Illuminate\Routing\Router $router */
            $router = app('router');
            $routes = $router->getRoutes();

            foreach ($routes as $route) {
                // Skip the fallback route
                if ($route->uri() === '{any}') {
                    continue;
                }

                // Check if this might be a module route
                $action = $route->getActionName();
                if (str_contains($action, 'Modules\\') || str_contains($route->uri(), 'wishlist')) {
                    $moduleRoutes[] = [
                        'uri' => $route->uri(),
                        'methods' => $route->methods(),
                        'name' => $route->getName(),
                        'action' => $action,
                        'middleware' => $route->middleware(),
                    ];
                }
            }

        } catch (\Exception $e) {
            // Silently fail
        }

        return $moduleRoutes;
    }
} 