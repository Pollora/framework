<?php

declare(strict_types=1);

namespace Pollora\Route\Infrastructure\Services;

use Illuminate\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Routing\Router as IlluminateRouter;
use Pollora\Route\Domain\Models\Route;

/**
 * Extended Laravel Router with WordPress condition support.
 *
 * This router extends Laravel's default routing capabilities by adding
 * support for WordPress conditional tags while maintaining complete
 * compatibility with standard Laravel routes.
 */
class ExtendedRouter extends IlluminateRouter
{
    /**
     * WordPress condition aliases.
     *
     * @var array<string, string>
     */
    protected array $conditions = [];

    /**
     * Whether WordPress conditions have been loaded.
     */
    protected bool $conditionsLoaded = false;

    /**
     * Create a new extended router instance.
     *
     * @param  Dispatcher  $events
     * @param  Container|null  $container
     */
    public function __construct(Dispatcher $events, Container $container = null)
    {
        parent::__construct($events, $container);
        // Don't load conditions in constructor - Laravel config may not be ready yet
    }

    /**
     * Create a new Route object.
     *
     * @param  array|string  $methods
     * @param  string  $uri
     * @param  mixed  $action
     * @return Route
     */
    public function newRoute($methods, $uri, $action): Route
    {
        return (new Route($methods, $uri, $action))
            ->setRouter($this)
            ->setContainer($this->container);
    }

    /**
     * Load WordPress condition aliases from configuration.
     * Uses lazy loading to ensure configuration is available.
     */
    protected function loadWordPressConditions(): void
    {
        if ($this->conditionsLoaded) {
            return;
        }

        $this->conditions = [];

        // Try to load from config if available
        try {
            if ($this->container && $this->container->bound('config')) {
                $config = $this->container->make('config');
                $configConditions = $config->get('wordpress.routing.conditions', []);

                if (!empty($configConditions)) {
                    // Merge config conditions with defaults, config takes precedence
                    $this->conditions = array_merge($this->conditions, $configConditions);
                }
            }
        } catch (\Exception) {
            // Silently ignore if config is not available yet
        }

        $this->conditionsLoaded = true;
    }

    /**
     * Get WordPress condition aliases.
     * Ensures conditions are loaded before returning them.
     *
     * @return array<string, string>
     */
    public function getConditions(): array
    {
        $this->loadWordPressConditions();
        return $this->conditions;
    }

    /**
     * Resolve a condition alias to the actual WordPress function.
     * Uses lazy loading to ensure configuration is available when needed.
     *
     * @param  string  $condition
     * @return string
     */
    public function resolveCondition(string $condition): string
    {
        $this->loadWordPressConditions();
        return $this->conditions[$condition] ?? $condition;
    }

    /**
     * Add WordPress dependency injection bindings to a route.
     * 
     * Analyzes the route action parameters and injects WordPress objects
     * based on their type hints (WP_Post, WP_Term, WP_User, WP_Query, etc.).
     *
     * @param  Route  $route
     * @return Route
     */
    public function addWordPressBindings(Route $route): Route
    {
        $action = $route->getAction();
        
        // Skip if no action or not a closure/callable
        if (!isset($action['uses']) || !is_callable($action['uses'])) {
            return $route;
        }

        try {
            // Get reflection of the callable
            $reflection = $this->getCallableReflection($action['uses']);
            if (!$reflection) {
                return $route;
            }

            // Analyze parameters and inject WordPress objects
            foreach ($reflection->getParameters() as $parameter) {
                $type = $parameter->getType();
                if (!$type || $type->isBuiltin()) {
                    continue;
                }

                $typeName = $type->getName();
                $value = $this->resolveWordPressType($typeName);
                
                if ($value !== null) {
                    $route->setParameter($parameter->getName(), $value);
                }
            }
        } catch (\Exception $e) {
            // Silently continue if reflection fails
        }

        return $route;
    }

    /**
     * Get reflection from a callable.
     */
    protected function getCallableReflection($callable): ?\ReflectionFunctionAbstract
    {
        if ($callable instanceof \Closure) {
            return new \ReflectionFunction($callable);
        }

        if (is_string($callable) && str_contains($callable, '@')) {
            [$class, $method] = explode('@', $callable, 2);
            return new \ReflectionMethod($class, $method);
        }

        if (is_array($callable) && count($callable) === 2) {
            return new \ReflectionMethod($callable[0], $callable[1]);
        }

        if (is_string($callable) && class_exists($callable)) {
            return new \ReflectionMethod($callable, '__invoke');
        }

        return null;
    }

    /**
     * Resolve WordPress object by type name.
     */
    protected function resolveWordPressType(string $typeName): mixed
    {
        global $post, $wp_query, $wp;

        return match ($typeName) {
            'WP_Post' => $this->resolveWPPost(),
            'WP_Term' => $this->resolveWPTerm(),
            'WP_User' => $this->resolveWPUser(),
            'WP_Query' => $wp_query,
            'WP' => $wp,
            default => null,
        };
    }

    /**
     * Resolve WP_Post object.
     */
    protected function resolveWPPost(): ?\WP_Post
    {
        global $post;

        // First try global post
        if ($post instanceof \WP_Post) {
            return $post;
        }

        // Try queried object if it's a post
        if (function_exists('get_queried_object')) {
            $queried = get_queried_object();
            if ($queried instanceof \WP_Post) {
                return $queried;
            }
        }

        return null;
    }

    /**
     * Resolve WP_Term object.
     */
    protected function resolveWPTerm(): ?\WP_Term
    {
        if (function_exists('get_queried_object')) {
            $queried = get_queried_object();
            if ($queried instanceof \WP_Term) {
                return $queried;
            }
        }

        return null;
    }

    /**
     * Resolve WP_User object.
     */
    protected function resolveWPUser(): ?\WP_User
    {
        if (function_exists('get_queried_object')) {
            $queried = get_queried_object();
            if ($queried instanceof \WP_User) {
                return $queried;
            }
        }

        // Try current user as fallback
        if (function_exists('wp_get_current_user')) {
            $current_user = wp_get_current_user();
            if ($current_user instanceof \WP_User && $current_user->ID > 0) {
                return $current_user;
            }
        }

        return null;
    }

}
