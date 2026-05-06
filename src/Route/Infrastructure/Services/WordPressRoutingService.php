<?php

declare(strict_types=1);

namespace Pollora\Route\Infrastructure\Services;

use Illuminate\Contracts\Container\Container;
use Pollora\Route\Domain\Models\Route;
use Pollora\Route\Infrastructure\Services\Contracts\WordPressConditionManagerInterface;
use Pollora\Route\Infrastructure\Services\Contracts\WordPressTypeResolverInterface;
use Psr\Log\LoggerInterface;

/**
 * Service responsible for WordPress-specific routing operations.
 *
 * Encapsulates condition resolution, WordPress type registration in the container,
 * and parameter binding for WordPress routes. Decoupled from the router itself
 * to follow single-responsibility principle.
 */
class WordPressRoutingService
{
    public function __construct(
        private readonly WordPressConditionManagerInterface $conditionManager,
        private readonly WordPressTypeResolverInterface $typeResolver,
        private readonly ?LoggerInterface $logger = null
    ) {}

    /**
     * Resolve a condition alias to the actual WordPress function name.
     *
     * @example resolveCondition('single') returns 'is_single'
     * @example resolveCondition('is_page') returns 'is_page'
     */
    public function resolveCondition(string $condition): string
    {
        return $this->conditionManager->resolveCondition($condition);
    }

    /**
     * Get all registered WordPress condition aliases.
     *
     * @return array<string, string>
     */
    public function getConditions(): array
    {
        return $this->conditionManager->getConditions();
    }

    /**
     * Register WordPress types in Laravel's dependency injection container.
     *
     * Binds WP_Post, WP_Term, WP_User, WP_Query, and WP as resolvable types,
     * enabling type-hinted injection in controller methods and closures.
     */
    public function registerWordPressTypes(Container $container): void
    {
        $typesToRegister = [
            'WP_Post' => $this->typeResolver->resolvePost(...),
            'WP_Term' => $this->typeResolver->resolveTerm(...),
            'WP_User' => $this->typeResolver->resolveUser(...),
            'WP_Query' => $this->typeResolver->resolveQuery(...),
            'WP' => $this->typeResolver->resolveWP(...),
        ];

        foreach ($typesToRegister as $type => $resolver) {
            $container->bind($type, $this->createSafeResolver($resolver));
        }
    }

    /**
     * Bind WordPress parameters to a route based on its action's type-hints.
     *
     * Inspects the route's callable via reflection and injects resolved WordPress
     * objects (WP_Post, WP_Term, etc.) as route parameters.
     */
    public function bindWordPressParameters(Route $route): void
    {
        try {
            $action = $route->getAction();

            if (! isset($action['uses']) || ! is_callable($action['uses'])) {
                return;
            }

            $reflection = $this->getCallableReflection($action['uses']);
            if (! $reflection instanceof \ReflectionFunctionAbstract) {
                return;
            }

            foreach ($reflection->getParameters() as $parameter) {
                $type = $parameter->getType();
                if (! $type instanceof \ReflectionNamedType) {
                    continue;
                }

                if ($type->isBuiltin()) {
                    continue;
                }

                $value = $this->typeResolver->resolve($type->getName());

                if ($value !== null) {
                    $route->setParameter($parameter->getName(), $value);
                }
            }
        } catch (\Throwable $throwable) {
            $this->logError('Failed to bind WordPress parameters', $throwable, [
                'route_uri' => $route->uri(),
            ]);
        }
    }

    /**
     * Get reflection information from a callable.
     */
    private function getCallableReflection(mixed $callable): ?\ReflectionFunctionAbstract
    {
        try {
            return match (true) {
                $callable instanceof \Closure => new \ReflectionFunction($callable),
                is_string($callable) && str_contains($callable, '@') => $this->getMethodReflection($callable),
                is_array($callable) && count($callable) === 2 => new \ReflectionMethod($callable[0], $callable[1]),
                is_string($callable) && class_exists($callable) => new \ReflectionMethod($callable, '__invoke'),
                default => null,
            };
        } catch (\ReflectionException $reflectionException) {
            $this->logError('Failed to get callable reflection', $reflectionException, ['callable' => $callable]);

            return null;
        }
    }

    private function getMethodReflection(string $callable): \ReflectionMethod
    {
        [$class, $method] = explode('@', $callable, 2);

        return new \ReflectionMethod($class, $method);
    }

    /**
     * Wrap a resolver callable to safely catch exceptions.
     */
    private function createSafeResolver(callable $resolver): \Closure
    {
        return function () use ($resolver) {
            try {
                return $resolver();
            } catch (\Throwable $throwable) {
                $this->logError('WordPress type resolution failed', $throwable);

                return null;
            }
        };
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function logError(string $message, \Throwable $exception, array $context = []): void
    {
        if (! $this->logger instanceof LoggerInterface) {
            return;
        }

        $context['exception'] = $exception;
        $this->logger->error($message, $context);
    }
}
