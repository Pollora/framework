<?php

declare(strict_types=1);

namespace Pollora\Route\Domain\Models;

use Pollora\Route\Domain\Exceptions\InvalidRouteConditionException;

/**
 * Domain model representing a route
 *
 * Encapsulates routing logic for both Laravel and WordPress routes
 * with support for conditional matching and priority resolution.
 */
final class Route
{
    private readonly string $id;

    private function __construct(
        private readonly string $uri,
        private readonly array $methods,
        private readonly RouteCondition $condition,
        private readonly mixed $action,
        private readonly int $priority = 0,
        private readonly array $middleware = [],
        private readonly bool $isWordPressRoute = false,
        private readonly array $metadata = []
    ) {
        $this->id = $this->generateId();
    }

    /**
     * Create a standard Laravel route
     */
    public static function laravel(
        string $uri,
        array $methods,
        mixed $action,
        array $middleware = []
    ): self {
        return new self(
            uri: $uri,
            methods: $methods,
            condition: RouteCondition::fromLaravel($uri),
            action: $action,
            middleware: $middleware
        );
    }

    /**
     * Create a WordPress route with condition
     */
    public static function wordpress(
        array $methods,
        RouteCondition $condition,
        mixed $action,
        int $priority = null,
        array $middleware = []
    ): self {
        $calculatedPriority = $priority ?? $condition->getSpecificity();

        return new self(
            uri: $condition->toUniqueIdentifier(),
            methods: $methods,
            condition: $condition,
            action: $action,
            priority: $calculatedPriority,
            middleware: $middleware,
            isWordPressRoute: true
        );
    }

    /**
     * Create a WordPress route from tag and parameters
     */
    public static function fromWordPressTag(
        array $methods,
        string $tag,
        array $parameters,
        mixed $action,
        array $middleware = []
    ): self {
        $condition = RouteCondition::fromWordPressTag($tag, $parameters);

        return self::wordpress(
            methods: $methods,
            condition: $condition,
            action: $action,
            middleware: $middleware
        );
    }

    /**
     * Check if this route matches the given context
     */
    public function matches(array $context): RouteMatch
    {
        if (!$this->matchesMethods($context['method'] ?? 'GET')) {
            return RouteMatch::failed();
        }

        if ($this->isWordPressRoute) {
            return $this->matchesWordPressCondition($context);
        }

        return $this->matchesLaravelRoute($context);
    }

    /**
     * Check if this route has priority over another route
     */
    public function hasPriorityOver(self $other): bool
    {
        // WordPress routes generally have higher priority than Laravel routes
        if ($this->isWordPressRoute && !$other->isWordPressRoute) {
            return true;
        }

        if (!$this->isWordPressRoute && $other->isWordPressRoute) {
            return false;
        }

        // Compare by priority score
        if ($this->priority !== $other->priority) {
            return $this->priority > $other->priority;
        }

        // If priorities are equal, compare condition specificity
        return $this->condition->isMoreSpecificThan($other->condition);
    }

    /**
     * Check if this is a WordPress route
     */
    public function isWordPressRoute(): bool
    {
        return $this->isWordPressRoute;
    }

    /**
     * Get the route ID
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Get the route URI
     */
    public function getUri(): string
    {
        return $this->uri;
    }

    /**
     * Get the HTTP methods
     */
    public function getMethods(): array
    {
        return $this->methods;
    }

    /**
     * Get the route condition
     */
    public function getCondition(): RouteCondition
    {
        return $this->condition;
    }

    /**
     * Get the route action
     */
    public function getAction(): mixed
    {
        return $this->action;
    }

    /**
     * Get the route priority
     */
    public function getPriority(): int
    {
        return $this->priority;
    }

    /**
     * Get the middleware
     */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    /**
     * Get metadata
     */
    public function getMetadata(string $key = null): mixed
    {
        if ($key === null) {
            return $this->metadata;
        }

        return $this->metadata[$key] ?? null;
    }

    /**
     * Create a new route with additional middleware
     */
    public function withMiddleware(array $middleware): self
    {
        return new self(
            $this->uri,
            $this->methods,
            $this->condition,
            $this->action,
            $this->priority,
            array_merge($this->middleware, $middleware),
            $this->isWordPressRoute,
            $this->metadata
        );
    }

    /**
     * Create a new route with metadata
     */
    public function withMetadata(array $metadata): self
    {
        return new self(
            $this->uri,
            $this->methods,
            $this->condition,
            $this->action,
            $this->priority,
            $this->middleware,
            $this->isWordPressRoute,
            array_merge($this->metadata, $metadata)
        );
    }

    /**
     * Generate a unique ID for this route
     */
    private function generateId(): string
    {
        $data = [
            'uri' => $this->uri,
            'methods' => $this->methods,
            'condition' => $this->condition->toUniqueIdentifier(),
            'is_wordpress' => $this->isWordPressRoute,
        ];

        return md5(serialize($data));
    }

    /**
     * Check if route matches HTTP methods
     */
    private function matchesMethods(string $method): bool
    {
        return in_array(strtoupper($method), array_map('strtoupper', $this->methods), true);
    }

    /**
     * Match WordPress conditional route
     */
    private function matchesWordPressCondition(array $context): RouteMatch
    {
        $isMatch = $this->condition->evaluate($context);

        if (!$isMatch) {
            return RouteMatch::failed();
        }

        return RouteMatch::success(
            route: $this,
            parameters: $context['parameters'] ?? [],
            priority: $this->priority,
            matchedBy: 'wordpress_condition'
        );
    }

    /**
     * Match Laravel route
     */
    private function matchesLaravelRoute(array $context): RouteMatch
    {
        // For Laravel routes, we delegate to Laravel's native matching
        // This is handled by the Infrastructure layer
        return RouteMatch::success(
            route: $this,
            parameters: $context['parameters'] ?? [],
            priority: $this->priority,
            matchedBy: 'laravel_pattern'
        );
    }
}
