<?php

declare(strict_types=1);

namespace Pollora\Route\Domain\Models;

/**
 * Value object representing a matched route
 * 
 * Contains information about a route that has been matched against
 * a request, including parameters and priority information.
 */
final class RouteMatch
{
    private function __construct(
        private readonly ?Route $route,
        private readonly array $parameters = [],
        private readonly bool $isMatched = false,
        private readonly int $priority = 0,
        private readonly string $matchedBy = 'unknown'
    ) {}

    /**
     * Create a successful route match
     */
    public static function success(
        Route $route,
        array $parameters = [],
        int $priority = 0,
        string $matchedBy = 'condition'
    ): self {
        return new self($route, $parameters, true, $priority, $matchedBy);
    }

    /**
     * Create a failed route match
     */
    public static function failed(): self
    {
        return new self(
            route: null,
            isMatched: false
        );
    }

    /**
     * Create a match from template hierarchy
     */
    public static function fromTemplateHierarchy(
        Route $route,
        array $parameters = [],
        int $priority = 0
    ): self {
        return new self($route, $parameters, true, $priority, 'template_hierarchy');
    }

    /**
     * Create a match from special request
     */
    public static function fromSpecialRequest(
        Route $route,
        array $parameters = [],
        int $priority = 1000
    ): self {
        return new self($route, $parameters, true, $priority, 'special_request');
    }

    /**
     * Check if the route was successfully matched
     */
    public function isMatched(): bool
    {
        return $this->isMatched;
    }

    /**
     * Get the matched route
     */
    public function getRoute(): ?Route
    {
        return $this->route;
    }

    /**
     * Get the matched parameters
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * Get a specific parameter value
     */
    public function getParameter(string $key, mixed $default = null): mixed
    {
        return $this->parameters[$key] ?? $default;
    }

    /**
     * Check if a parameter exists
     */
    public function hasParameter(string $key): bool
    {
        return array_key_exists($key, $this->parameters);
    }

    /**
     * Get the match priority
     */
    public function getPriority(): int
    {
        return $this->priority;
    }

    /**
     * Get what matched this route
     */
    public function getMatchedBy(): string
    {
        return $this->matchedBy;
    }

    /**
     * Check if this match has higher priority than another
     */
    public function hasPriorityOver(self $other): bool
    {
        // If priorities are equal, use route specificity
        if ($this->priority === $other->priority) {
            if ($this->route === null || $other->route === null) {
                return false;
            }
            return $this->route->hasPriorityOver($other->route);
        }

        return $this->priority > $other->priority;
    }

    /**
     * Check if this is a WordPress route match
     */
    public function isWordPressRoute(): bool
    {
        return $this->route?->isWordPressRoute() ?? false;
    }

    /**
     * Check if this match was from template hierarchy
     */
    public function isFromTemplateHierarchy(): bool
    {
        return $this->matchedBy === 'template_hierarchy';
    }

    /**
     * Check if this match was from a special request
     */
    public function isFromSpecialRequest(): bool
    {
        return $this->matchedBy === 'special_request';
    }

    /**
     * Create a new match with additional parameters
     */
    public function withParameters(array $parameters): self
    {
        return new self(
            $this->route,
            array_merge($this->parameters, $parameters),
            $this->isMatched,
            $this->priority,
            $this->matchedBy
        );
    }

    /**
     * Create a new match with a different priority
     */
    public function withPriority(int $priority): self
    {
        return new self(
            $this->route,
            $this->parameters,
            $this->isMatched,
            $priority,
            $this->matchedBy
        );
    }

    /**
     * Convert match to array representation
     */
    public function toArray(): array
    {
        return [
            'route_id' => $this->route?->getId(),
            'parameters' => $this->parameters,
            'is_matched' => $this->isMatched,
            'priority' => $this->priority,
            'matched_by' => $this->matchedBy,
            'is_wordpress_route' => $this->isWordPressRoute(),
        ];
    }
}