<?php

declare(strict_types=1);

namespace Pollora\Route\Application\UseCases;

use Illuminate\Routing\Route;
use Pollora\Route\Infrastructure\Services\Contracts\WordPressTypeResolverInterface;
use Psr\Log\LoggerInterface;

/**
 * Use case for binding WordPress parameters to a route based on type-hints.
 *
 * Inspects the route's callable via reflection and injects resolved WordPress
 * objects (WP_Post, WP_Term, etc.) as route parameters.
 */
class BindWordPressParametersUseCase
{
    public function __construct(
        private readonly WordPressTypeResolverInterface $typeResolver,
        private readonly ?LoggerInterface $logger = null
    ) {}

    /**
     * Execute the use case to bind WordPress parameters to a route.
     */
    public function execute(Route $route): void
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
            $this->logger?->error('Failed to bind WordPress parameters', [
                'exception' => $throwable,
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
            $this->logger?->error('Failed to get callable reflection', [
                'exception' => $reflectionException,
                'callable' => $callable,
            ]);

            return null;
        }
    }

    private function getMethodReflection(string $callable): \ReflectionMethod
    {
        [$class, $method] = explode('@', $callable, 2);

        return new \ReflectionMethod($class, $method);
    }
}
