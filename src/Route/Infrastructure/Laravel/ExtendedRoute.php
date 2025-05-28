<?php

declare(strict_types=1);

namespace Pollora\Route\Infrastructure\Laravel;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Pollora\Route\Domain\Contracts\ConditionResolverInterface;
use Pollora\Route\UI\Http\Middleware\WordPressBindings;
use Pollora\Route\UI\Http\Middleware\WordPressBodyClass;
use Pollora\Route\UI\Http\Middleware\WordPressHeaders;
use Pollora\Route\UI\Http\Middleware\WordPressShutdown;

/**
 * Extended Laravel Route with WordPress support
 *
 * Extends Laravel's Route class to add WordPress conditional logic
 * while maintaining full compatibility with Laravel routing.
 */
final class ExtendedRoute extends Route
{
    private bool $isWordPressRoute = false;

    private string $wordpressCondition = '';

    private array $conditionParameters = [];

    private ?ConditionResolverInterface $conditionResolver = null;


    /**
     * Determine if the route matches the request
     */
    public function matches(Request $request, $includingMethod = true): bool
    {
        $this->compileRoute();

        // For WordPress routes, check WordPress conditions
        if ($this->isWordPressRoute && $this->hasWordPressCondition()) {
            return $this->matchesWordPressConditions($request, $includingMethod);
        }

        // For regular Laravel routes, use parent matching
        return parent::matches($request, $includingMethod);
    }

    /**
     * Mark this route as a WordPress route
     */
    public function setIsWordPressRoute(): self
    {
        $this->isWordPressRoute = true;

        return $this;
    }

    /**
     * Check if this is a WordPress route
     */
    public function isWordPressRoute(): bool
    {
        return $this->isWordPressRoute;
    }

    /**
     * Set the WordPress condition for this route
     */
    public function setWordPressCondition(string $condition): self
    {
        $this->wordpressCondition = $condition;

        return $this;
    }

    /**
     * Get the WordPress condition
     */
    public function getWordPressCondition(): string
    {
        return $this->wordpressCondition;
    }

    /**
     * Set condition parameters
     */
    public function setConditionParameters(array $parameters): self
    {
        $this->conditionParameters = $parameters;

        return $this;
    }

    /**
     * Get condition parameters
     */
    public function getConditionParameters(): array
    {
        return $this->conditionParameters;
    }

    /**
     * Set the condition resolver
     */
    public function setConditionResolver(ConditionResolverInterface $resolver): self
    {
        $this->conditionResolver = $resolver;

        return $this;
    }


    /**
     * Check if this route handles special WordPress requests
     */
    public function isSpecialRequestRoute(): bool
    {
        if (! $this->isWordPressRoute) {
            return false;
        }

        $specialConditions = ['is_robots', 'is_favicon', 'is_feed', 'is_trackback', 'is_xmlrpc'];

        return in_array($this->wordpressCondition, $specialConditions, true);
    }

    /**
     * Get route priority based on WordPress condition specificity
     */
    public function getWordPressPriority(): int
    {
        if (! $this->isWordPressRoute) {
            return 0;
        }

        $basePriority = match ($this->wordpressCondition) {
            'is_front_page' => 1000,
            'is_home' => 900,
            'is_page' => 800,
            'is_single' => 700,
            'is_category' => 600,
            'is_tag' => 500,
            'is_archive' => 400,
            'is_404' => 300,
            'is_search' => 200,
            default => 100
        };

        // Add parameter specificity bonus
        return $basePriority + (count($this->conditionParameters) * 50);
    }

    /**
     * Check if route has WordPress condition
     */
    public function hasWordPressCondition(): bool
    {
        return ! empty($this->wordpressCondition);
    }

    /**
     * Get route information for debugging
     */
    public function getRouteInfo(): array
    {
        return [
            'uri' => $this->uri(),
            'methods' => $this->methods(),
            'action' => $this->getAction(),
            'is_wordpress_route' => $this->isWordPressRoute,
            'wordpress_condition' => $this->wordpressCondition,
            'condition_parameters' => $this->conditionParameters,
            'middleware' => $this->middleware(),
            'priority' => $this->getWordPressPriority(),
            'is_special_request' => $this->isSpecialRequestRoute(),
        ];
    }

    /**
     * Apply WordPress-specific middleware automatically
     */
    public function applyWordPressMiddleware(): self
    {
        if (! $this->isWordPressRoute) {
            return $this;
        }

        $wordpressMiddleware = [
            WordPressBindings::class,
            WordPressHeaders::class,
            WordPressBodyClass::class,
            WordPressShutdown::class,
        ];

        return $this->middleware($wordpressMiddleware);
    }

    /**
     * Check WordPress conditions for route matching
     */
    private function matchesWordPressConditions(Request $request, bool $includingMethod): bool
    {
        // First check HTTP method if required
        if ($includingMethod && ! $this->matchesMethod($request->getMethod())) {
            return false;
        }

        // Check WordPress condition (which includes template priority checking)
        return $this->evaluateWordPressCondition();
    }

    /**
     * Evaluate the WordPress condition
     */
    private function evaluateWordPressCondition(): bool
    {
        if (empty($this->wordpressCondition)) {
            return false;
        }

        // First check if WordPress condition is satisfied
        if ($this->conditionResolver) {
            $conditionMet = $this->conditionResolver->resolve(
                $this->wordpressCondition,
                $this->conditionParameters
            );
        } else {
            // Fallback to direct function call
            $conditionMet = $this->callWordPressFunction();
        }

        // If condition is not met, route doesn't match
        if (! $conditionMet) {
            return false;
        }

        // Routes always take priority over templates, no need to check template override

        return true;
    }




    /**
     * Call WordPress function directly
     */
    private function callWordPressFunction(): bool
    {
        $condition = $this->wordpressCondition;

        // Check if function exists
        if (! function_exists($condition)) {
            return false;
        }

        try {
            // Call WordPress function with parameters
            return (bool) call_user_func_array($condition, $this->conditionParameters);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Check if request method matches route methods
     */
    private function matchesMethod(string $method): bool
    {
        return in_array(strtoupper($method), $this->methods(), true);
    }

    /**
     * Override bind method to handle WordPress parameter binding
     */
    public function bind(Request $request)
    {
        $this->compileRoute();

        // For WordPress routes, we might have different parameter binding logic
        if ($this->isWordPressRoute) {
            $this->bindWordPressParameters($request);
        }

        return parent::bind($request);
    }

    /**
     * Bind WordPress-specific parameters
     */
    private function bindWordPressParameters(Request $request): void
    {
        global $post, $wp_query;

        // Bind WordPress globals as parameters
        $this->parameters['wp_post'] = $post ?? null;
        $this->parameters['wp_query'] = $wp_query ?? null;

        // Bind condition parameters
        foreach ($this->conditionParameters as $index => $parameter) {
            $this->parameters["condition_param_{$index}"] = $parameter;
        }

        // Bind WordPress-specific request data
        if (function_exists('get_queried_object')) {
            $this->parameters['queried_object'] = get_queried_object();
        }

        if (function_exists('get_queried_object_id')) {
            $this->parameters['queried_object_id'] = get_queried_object_id();
        }
    }
}
