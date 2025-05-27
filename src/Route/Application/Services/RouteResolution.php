<?php

declare(strict_types=1);

namespace Pollora\Route\Application\Services;

use Pollora\Route\Domain\Models\RouteMatch;
use Pollora\Route\Domain\Models\SpecialRequest;
use Pollora\Route\Domain\Models\TemplateHierarchy;

/**
 * Value object representing the result of route resolution
 * 
 * Contains information about how a request was resolved, whether through
 * a route match, template hierarchy, special request, or not at all.
 */
final class RouteResolution
{
    private function __construct(
        private readonly bool $isResolved,
        private readonly string $type,
        private readonly ?RouteMatch $routeMatch = null,
        private readonly ?TemplateHierarchy $templateHierarchy = null,
        private readonly ?string $templatePath = null,
        private readonly ?SpecialRequest $specialRequest = null,
        private readonly mixed $response = null,
        private readonly array $metadata = []
    ) {}

    /**
     * Create resolution from route match
     */
    public static function fromRouteMatch(RouteMatch $match, string $type = 'route'): self
    {
        return new self(
            isResolved: $match->isMatched(),
            type: $type,
            routeMatch: $match,
            metadata: ['matched_by' => $match->getMatchedBy()]
        );
    }

    /**
     * Create resolution from template hierarchy
     */
    public static function fromTemplate(
        TemplateHierarchy $hierarchy,
        string $templatePath,
        string $type = 'template'
    ): self {
        return new self(
            isResolved: true,
            type: $type,
            templateHierarchy: $hierarchy,
            templatePath: $templatePath,
            metadata: ['template_condition' => $hierarchy->getCondition()]
        );
    }

    /**
     * Create resolution from special request
     */
    public static function fromSpecialResponse(
        SpecialRequest $specialRequest,
        mixed $response,
        string $type = 'special'
    ): self {
        return new self(
            isResolved: true,
            type: $type,
            specialRequest: $specialRequest,
            response: $response,
            metadata: ['special_type' => $specialRequest->getType()]
        );
    }

    /**
     * Create unresolved (404) result
     */
    public static function notFound(): self
    {
        return new self(
            isResolved: false,
            type: 'not_found',
            metadata: ['status_code' => 404]
        );
    }

    /**
     * Check if request was successfully resolved
     */
    public function isResolved(): bool
    {
        return $this->isResolved;
    }

    /**
     * Get the resolution type
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Check if resolution is from a route match
     */
    public function isRouteMatch(): bool
    {
        return $this->routeMatch !== null;
    }

    /**
     * Check if resolution is from template hierarchy
     */
    public function isTemplateHierarchy(): bool
    {
        return $this->templateHierarchy !== null;
    }

    /**
     * Check if resolution is from special request
     */
    public function isSpecialRequest(): bool
    {
        return $this->specialRequest !== null;
    }

    /**
     * Check if this is a 404 result
     */
    public function isNotFound(): bool
    {
        return $this->type === 'not_found';
    }

    /**
     * Get the route match if available
     */
    public function getRouteMatch(): ?RouteMatch
    {
        return $this->routeMatch;
    }

    /**
     * Get the template hierarchy if available
     */
    public function getTemplateHierarchy(): ?TemplateHierarchy
    {
        return $this->templateHierarchy;
    }

    /**
     * Get the template path if available
     */
    public function getTemplatePath(): ?string
    {
        return $this->templatePath;
    }

    /**
     * Get the special request if available
     */
    public function getSpecialRequest(): ?SpecialRequest
    {
        return $this->specialRequest;
    }

    /**
     * Get the response if available
     */
    public function getResponse(): mixed
    {
        return $this->response;
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
     * Check if resolution has an action to execute
     */
    public function hasAction(): bool
    {
        return $this->routeMatch?->getRoute()->getAction() !== null;
    }

    /**
     * Execute the resolved action if available
     */
    public function executeAction(): mixed
    {
        if (!$this->hasAction()) {
            return null;
        }

        $route = $this->routeMatch->getRoute();
        $action = $route->getAction();
        $parameters = $this->routeMatch->getParameters();

        // Handle different action types
        if (is_callable($action)) {
            return call_user_func($action, ...$parameters);
        }

        if (is_string($action) && class_exists($action)) {
            $instance = new $action();
            if (method_exists($instance, '__invoke')) {
                return $instance(...$parameters);
            }
        }

        if (is_array($action) && count($action) === 2) {
            [$controller, $method] = $action;
            if (is_string($controller) && class_exists($controller)) {
                $instance = new $controller();
                if (method_exists($instance, $method)) {
                    return $instance->$method(...$parameters);
                }
            }
        }

        return $action;
    }

    /**
     * Get the HTTP status code for this resolution
     */
    public function getStatusCode(): int
    {
        if (!$this->isResolved) {
            return 404;
        }

        return match ($this->type) {
            'special_explicit', 'special_default' => 200,
            'route_match', 'template_hierarchy', 'template_override' => 200,
            default => 404
        };
    }

    /**
     * Convert to array representation
     */
    public function toArray(): array
    {
        return [
            'is_resolved' => $this->isResolved,
            'type' => $this->type,
            'status_code' => $this->getStatusCode(),
            'has_action' => $this->hasAction(),
            'route_match' => $this->routeMatch?->toArray(),
            'template_hierarchy' => $this->templateHierarchy?->toArray(),
            'template_path' => $this->templatePath,
            'special_request' => $this->specialRequest?->toArray(),
            'metadata' => $this->metadata,
        ];
    }

    /**
     * Create a new resolution with additional metadata
     */
    public function withMetadata(array $metadata): self
    {
        return new self(
            $this->isResolved,
            $this->type,
            $this->routeMatch,
            $this->templateHierarchy,
            $this->templatePath,
            $this->specialRequest,
            $this->response,
            array_merge($this->metadata, $metadata)
        );
    }
}