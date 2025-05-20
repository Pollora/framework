<?php

declare(strict_types=1);

namespace Pollora\Route\Domain\Models;

/**
 * Represents a route entity in the domain layer.
 * 
 * This is a framework-agnostic representation of a route with WordPress
 * condition capabilities.
 */
class RouteEntity
{
    /**
     * @var array<int, string>
     */
    private array $methods = [];

    /**
     * @var string
     */
    private string $uri = '';
    
    /**
     * @var mixed
     */
    private $action;
    
    /**
     * @var array<string, mixed>
     */
    private array $parameters = [];
    
    /**
     * @var string
     */
    private string $domain = '';
    
    /**
     * @var bool
     */
    private bool $isWordPressRoute = false;
    
    /**
     * @var string
     */
    private string $condition = '';
    
    /**
     * @var array<mixed>
     */
    private array $conditionParameters = [];

    /**
     * @param array<int, string> $methods
     * @param string $uri
     * @param mixed $action
     */
    public function __construct(array $methods, string $uri, $action)
    {
        $this->methods = $methods;
        $this->uri = $uri;
        $this->action = $action;
    }

    /**
     * @return array<int, string>
     */
    public function getMethods(): array
    {
        return $this->methods;
    }

    /**
     * @return string
     */
    public function getUri(): string
    {
        return $this->uri;
    }

    /**
     * @return mixed
     */
    public function getAction()
    {
        return $this->action;
    }

    /**
     * @param string $domain
     * @return self
     */
    public function setDomain(string $domain): self
    {
        $this->domain = $domain;
        return $this;
    }

    /**
     * @return string
     */
    public function getDomain(): string
    {
        return $this->domain;
    }

    /**
     * @param array<string, mixed> $parameters
     * @return self
     */
    public function setParameters(array $parameters): self
    {
        $this->parameters = $parameters;
        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * @param string $name
     * @param mixed $value
     * @return self
     */
    public function setParameter(string $name, $value): self
    {
        $this->parameters[$name] = $value;
        return $this;
    }

    /**
     * Check if this route was created using the wordpress macro.
     *
     * @return bool True if this is a WordPress route, false otherwise
     */
    public function isWordPressRoute(): bool
    {
        return $this->isWordPressRoute;
    }

    /**
     * Mark this route as a WordPress route.
     *
     * @param bool $isWordPressRoute Whether this is a WordPress route
     * @return self Returns the current instance for method chaining
     */
    public function setIsWordPressRoute(bool $isWordPressRoute = true): self
    {
        $this->isWordPressRoute = $isWordPressRoute;
        return $this;
    }

    /**
     * Get the current WordPress condition signature.
     *
     * @return string The current condition signature
     */
    public function getCondition(): string
    {
        return $this->condition;
    }

    /**
     * Set the current WordPress condition.
     * 
     * @param string $condition The condition to set
     * @return self
     */
    public function setCondition(string $condition): self
    {
        $this->condition = $condition;
        return $this;
    }

    /**
     * Check if the route has a WordPress condition.
     *
     * @return bool True if a valid condition is set, false otherwise
     */
    public function hasCondition(): bool
    {
        return $this->condition !== '' && $this->condition !== '0';
    }

    /**
     * Get parameters for the current condition.
     *
     * @return array<mixed> The current condition parameters
     */
    public function getConditionParameters(): array
    {
        return $this->conditionParameters;
    }

    /**
     * Set parameters for the current condition.
     *
     * @param array<mixed> $parameters The parameters to set for the condition
     * @return self Returns the current instance for method chaining
     */
    public function setConditionParameters(array $parameters = []): self
    {
        $this->conditionParameters = $parameters;
        return $this;
    }
} 