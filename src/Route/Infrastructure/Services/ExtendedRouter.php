<?php

declare(strict_types=1);

namespace Pollora\Route\Infrastructure\Services;

use Illuminate\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Routing\Router as IlluminateRouter;
use Pollora\Route\Domain\Models\Route;
use Pollora\Route\Infrastructure\Services\Contracts\WordPressConditionManagerInterface;
use Pollora\Route\Infrastructure\Services\Contracts\WordPressTypeResolverInterface;
use Psr\Log\LoggerInterface;

/**
 * Extended Laravel Router with WordPress condition support.
 *
 * This router extends Laravel's default routing capabilities by adding
 * support for WordPress conditional tags while maintaining complete
 * compatibility with standard Laravel routes.
 */
class ExtendedRouter extends IlluminateRouter
{
    private WordPressConditionManagerInterface $conditionManager;

    private WordPressTypeResolverInterface $typeResolver;

    private ?LoggerInterface $logger;

    /**
     * Create a new extended router instance.
     */
    public function __construct(
        Dispatcher $events,
        ?Container $container = null,
        ?WordPressConditionManagerInterface $conditionManager = null,
        ?WordPressTypeResolverInterface $typeResolver = null,
        ?LoggerInterface $logger = null
    ) {
        parent::__construct($events, $container);

        $this->conditionManager = $conditionManager ?? $this->createDefaultConditionManager();
        $this->typeResolver = $typeResolver ?? new Resolvers\WordPressTypeResolver;
        $this->logger = $logger;

        $this->registerWordPressTypesInContainer();
    }

    /**
     * Create a new Route object.
     *
     * @param  array|string  $methods
     * @param  string  $uri
     * @param  mixed  $action
     */
    public function newRoute($methods, $uri, $action): Route
    {
        return (new Route($methods, $uri, $action))
            ->setRouter($this)
            ->setContainer($this->container);
    }

    /**
     * Get WordPress condition aliases.
     *
     * @return array<string, string>
     */
    public function getConditions(): array
    {
        return $this->conditionManager->getConditions();
    }

    /**
     * Resolve a condition alias to the actual WordPress function.
     */
    public function resolveCondition(string $condition): string
    {
        return $this->conditionManager->resolveCondition($condition);
    }

    /**
     * Add WordPress dependency injection bindings to a route.
     */
    public function addWordPressBindings(Route $route): Route
    {
        try {
            $action = $route->getAction();

            if (! $this->isValidActionForBinding($action)) {
                return $route;
            }

            $reflection = $this->getCallableReflection($action['uses']);
            if (! $reflection) {
                return $route;
            }

            $this->bindWordPressParametersToRoute($route, $reflection);

        } catch (\Throwable $e) {
            $this->logError('Failed to add WordPress bindings', $e, [
                'route_uri' => $route->uri(),
                'route_methods' => $route->methods(),
            ]);
        }

        return $route;
    }

    /**
     * Check if an action array is valid for WordPress parameter binding.
     *
     * @param  array  $action  Route action definition
     * @return bool True when a callable "uses" entry exists
     */
    private function isValidActionForBinding(array $action): bool
    {
        return isset($action['uses']) && is_callable($action['uses']);
    }

    /**
     * Bind WordPress parameters to a route based on reflection data.
     *
     * @param  Route  $route  Route instance to bind
     * @param  \ReflectionFunctionAbstract  $reflection  Callable reflection for parameter inspection
     */
    private function bindWordPressParametersToRoute(Route $route, \ReflectionFunctionAbstract $reflection): void
    {
        foreach ($reflection->getParameters() as $parameter) {
            $type = $parameter->getType();

            if (! $type || $type->isBuiltin()) {
                continue;
            }

            $typeName = $type->getName();
            $value = $this->typeResolver->resolve($typeName);

            if ($value !== null) {
                $route->setParameter($parameter->getName(), $value);
            }
        }
    }

    /**
     * Get reflection information from a callable with error handling.
     *
     * @param  mixed  $callable  The callable to reflect
     * @return \ReflectionFunctionAbstract|null Reflection object or null on failure
     */
    protected function getCallableReflection($callable): ?\ReflectionFunctionAbstract
    {
        try {
            return match (true) {
                $callable instanceof \Closure => new \ReflectionFunction($callable),
                is_string($callable) && str_contains($callable, '@') => $this->getMethodReflection($callable),
                is_array($callable) && count($callable) === 2 => new \ReflectionMethod($callable[0], $callable[1]),
                is_string($callable) && class_exists($callable) => new \ReflectionMethod($callable, '__invoke'),
                default => null,
            };
        } catch (\ReflectionException $e) {
            $this->logError('Failed to get callable reflection', $e, ['callable' => $callable]);

            return null;
        }
    }

    /**
     * Get a method reflection from string format (Class@method).
     *
     * @param  string  $callable  Callable string in Class@method form
     * @return \ReflectionMethod Reflection of the specified method
     */
    private function getMethodReflection(string $callable): \ReflectionMethod
    {
        [$class, $method] = explode('@', $callable, 2);

        return new \ReflectionMethod($class, $method);
    }

    /**
     * Create the default WordPress condition manager when none is provided.
     *
     * @return WordPressConditionManagerInterface Condition manager instance
     */
    private function createDefaultConditionManager(): WordPressConditionManagerInterface
    {
        return new WordPressConditionManager($this->container);
    }

    /**
     * Log an error with context if a logger is available.
     *
     * @param  string  $message  Error message
     * @param  \Throwable  $exception  Exception instance
     * @param  array  $context  Additional logging context
     */
    private function logError(string $message, \Throwable $exception, array $context = []): void
    {
        if (! $this->logger) {
            return;
        }

        $context['exception'] = $exception;
        $this->logger->error($message, $context);
    }

    /**
     * Wrap a resolver callable to safely catch exceptions.
     *
     * @param  callable  $resolver  Resolver to wrap
     * @return \Closure Safe wrapper closure
     */
    private function createSafeResolver(callable $resolver): \Closure
    {
        return function () use ($resolver) {
            try {
                return $resolver();
            } catch (\Throwable $e) {
                $this->logError('WordPress type resolution failed', $e);

                return null;
            }
        };
    }

    /**
     * Register WordPress types in Laravel's dependency injection container.
     *
     * This allows Laravel to resolve WordPress types like WP_Post, WP_Term, etc.
     * when they are type-hinted in controller methods or closures.
     */
    protected function registerWordPressTypesInContainer(): void
    {
        if (! $this->container) {
            return;
        }

        $typesToRegister = [
            'WP_Post' => fn () => $this->typeResolver->resolvePost(),
            'WP_Term' => fn () => $this->typeResolver->resolveTerm(),
            'WP_User' => fn () => $this->typeResolver->resolveUser(),
            'WP_Query' => fn () => $this->typeResolver->resolveQuery(),
            'WP' => fn () => $this->typeResolver->resolveWP(),
        ];

        foreach ($typesToRegister as $type => $resolver) {
            $this->container->bind($type, $this->createSafeResolver($resolver));
        }
    }
}
