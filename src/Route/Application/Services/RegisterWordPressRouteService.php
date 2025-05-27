<?php

declare(strict_types=1);

namespace Pollora\Route\Application\Services;

use Pollora\Route\Domain\Contracts\ConditionResolverInterface;
use Pollora\Route\Domain\Contracts\RouteRegistryInterface;
use Pollora\Route\Domain\Models\Route;
use Pollora\Route\Domain\Models\RouteCondition;
use Pollora\Route\Domain\Services\ConditionValidator;

/**
 * Service for registering WordPress routes
 * 
 * Handles the registration of WordPress-specific routes with proper
 * condition validation and middleware application.
 */
final class RegisterWordPressRouteService
{
    public function __construct(
        private readonly RouteRegistryInterface $registry,
        private readonly ConditionResolverInterface $conditionResolver,
        private readonly ConditionValidator $conditionValidator,
        private readonly array $config = []
    ) {}

    /**
     * Register a WordPress route
     * 
     * @param array $methods HTTP methods for the route
     * @param string $condition WordPress conditional tag
     * @param array $parameters Parameters for the condition
     * @param mixed $action Route action (controller, closure, etc.)
     * @param array $options Additional route options
     * @return Route The registered route
     */
    public function execute(
        array $methods,
        string $condition,
        array $parameters,
        mixed $action,
        array $options = []
    ): Route {
        // Resolve condition alias if necessary
        $resolvedCondition = $this->conditionResolver->resolveAlias($condition);
        
        // Create route condition
        $routeCondition = RouteCondition::fromWordPressTag($resolvedCondition, $parameters);
        
        // Validate the condition
        $this->conditionValidator->validate($routeCondition);
        
        // Apply default WordPress middleware
        $middleware = $this->mergeMiddleware($options['middleware'] ?? []);
        
        // Create the route
        $route = Route::wordpress(
            methods: $methods,
            condition: $routeCondition,
            action: $action,
            priority: $options['priority'] ?? null,
            middleware: $middleware
        );
        
        // Add metadata from options
        if (!empty($options['metadata'])) {
            $route = $route->withMetadata($options['metadata']);
        }
        
        // Register the route
        $this->registry->register($route);
        
        return $route;
    }

    /**
     * Register multiple WordPress routes from configuration
     * 
     * @param array $routeDefinitions Array of route definitions
     * @return Route[] Array of registered routes
     */
    public function registerMultiple(array $routeDefinitions): array
    {
        $routes = [];
        
        foreach ($routeDefinitions as $definition) {
            $routes[] = $this->execute(
                methods: $definition['methods'] ?? ['GET'],
                condition: $definition['condition'],
                parameters: $definition['parameters'] ?? [],
                action: $definition['action'],
                options: $definition['options'] ?? []
            );
        }
        
        return $routes;
    }

    /**
     * Register a WordPress route with all HTTP methods
     * 
     * @param string $condition WordPress conditional tag
     * @param array $parameters Parameters for the condition
     * @param mixed $action Route action
     * @param array $options Additional options
     * @return Route The registered route
     */
    public function registerAny(
        string $condition,
        array $parameters,
        mixed $action,
        array $options = []
    ): Route {
        return $this->execute(
            methods: ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
            condition: $condition,
            parameters: $parameters,
            action: $action,
            options: $options
        );
    }

    /**
     * Register a GET WordPress route
     * 
     * @param string $condition WordPress conditional tag
     * @param array $parameters Parameters for the condition
     * @param mixed $action Route action
     * @param array $options Additional options
     * @return Route The registered route
     */
    public function registerGet(
        string $condition,
        array $parameters,
        mixed $action,
        array $options = []
    ): Route {
        return $this->execute(
            methods: ['GET'],
            condition: $condition,
            parameters: $parameters,
            action: $action,
            options: $options
        );
    }

    /**
     * Register a POST WordPress route
     * 
     * @param string $condition WordPress conditional tag
     * @param array $parameters Parameters for the condition
     * @param mixed $action Route action
     * @param array $options Additional options
     * @return Route The registered route
     */
    public function registerPost(
        string $condition,
        array $parameters,
        mixed $action,
        array $options = []
    ): Route {
        return $this->execute(
            methods: ['POST'],
            condition: $condition,
            parameters: $parameters,
            action: $action,
            options: $options
        );
    }

    /**
     * Register routes from configuration file
     * 
     * @param string $configKey Configuration key to load routes from
     * @return Route[] Array of registered routes
     */
    public function registerFromConfig(string $configKey = 'wordpress.routes'): array
    {
        $routeDefinitions = $this->config[$configKey] ?? [];
        
        return $this->registerMultiple($routeDefinitions);
    }

    /**
     * Validate route parameters before registration
     * 
     * @param array $methods HTTP methods
     * @param string $condition WordPress condition
     * @param array $parameters Condition parameters
     * @param mixed $action Route action
     * @return bool True if all parameters are valid
     */
    public function validateRouteParameters(
        array $methods,
        string $condition,
        array $parameters,
        mixed $action
    ): bool {
        // Validate HTTP methods
        if (empty($methods)) {
            return false;
        }
        
        $validMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD'];
        foreach ($methods as $method) {
            if (!in_array(strtoupper($method), $validMethods, true)) {
                return false;
            }
        }
        
        // Validate condition
        if (empty($condition)) {
            return false;
        }
        
        try {
            $routeCondition = RouteCondition::fromWordPressTag($condition, $parameters);
            $this->conditionValidator->validate($routeCondition);
        } catch (\Exception) {
            return false;
        }
        
        // Validate action
        if ($action === null) {
            return false;
        }
        
        return true;
    }

    /**
     * Merge default WordPress middleware with provided middleware
     * 
     * @param array $providedMiddleware User-provided middleware
     * @return array Merged middleware array
     */
    private function mergeMiddleware(array $providedMiddleware): array
    {
        $defaultWordPressMiddleware = $this->config['routing']['middleware']['wordpress'] ?? [
            'Pollora\Route\UI\Http\Middleware\WordPressBindings',
            'Pollora\Route\UI\Http\Middleware\WordPressHeaders',
            'Pollora\Route\UI\Http\Middleware\WordPressBodyClass',
            'Pollora\Route\UI\Http\Middleware\WordPressShutdown',
        ];
        
        return array_merge($defaultWordPressMiddleware, $providedMiddleware);
    }
}