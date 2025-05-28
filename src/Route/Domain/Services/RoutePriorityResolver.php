<?php

declare(strict_types=1);

namespace Pollora\Route\Domain\Services;

use Pollora\Route\Domain\Models\Route;
use Pollora\Route\Domain\Models\RouteMatch;
use Pollora\Route\Domain\Models\TemplateHierarchy;

/**
 * Service for resolving route priorities
 *
 * Determines which route should take precedence when multiple
 * routes could potentially match a request.
 */
final class RoutePriorityResolver
{
    /**
     * Priority levels for different route types
     */
    private const PRIORITY_LEVELS = [
        'special_request' => 2000,
        'laravel_explicit' => 1500,
        'wordpress_specific' => 1000,
        'wordpress_generic' => 500,
        'template_hierarchy' => 100,
        'fallback' => 0,
    ];

    /**
     * Resolve the highest priority route from multiple matches
     *
     * @param RouteMatch[] $matches Array of route matches
     * @return RouteMatch|null The highest priority match
     */
    public function resolveHighestPriority(array $matches): ?RouteMatch
    {
        if (empty($matches)) {
            return null;
        }

        // Filter out failed matches
        $validMatches = array_filter($matches, fn(RouteMatch $match) => $match->isMatched());

        if (empty($validMatches)) {
            return null;
        }

        // Sort by priority (highest first)
        usort($validMatches, [$this, 'compareMatches']);

        return $validMatches[0];
    }

    /**
     * Compare two route matches for priority ordering
     *
     * @param RouteMatch $a First match
     * @param RouteMatch $b Second match
     * @return int Comparison result (-1, 0, 1)
     */
    public function compareMatches(RouteMatch $a, RouteMatch $b): int
    {
        // First compare by explicit priority
        if ($a->getPriority() !== $b->getPriority()) {
            return $b->getPriority() <=> $a->getPriority();
        }

        // Then compare by match type priority
        $typeA = $this->getMatchTypePriority($a);
        $typeB = $this->getMatchTypePriority($b);

        if ($typeA !== $typeB) {
            return $typeB <=> $typeA;
        }

        // Finally compare by route specificity
        return $a->getRoute()->hasPriorityOver($b->getRoute()) ? -1 : 1;
    }

    /**
     * Compare routes directly for priority
     *
     * @param Route $a First route
     * @param Route $b Second route
     * @return int Comparison result (-1, 0, 1)
     */
    public function compareRoutes(Route $a, Route $b): int
    {
        // WordPress routes generally have priority over Laravel routes
        if ($a->isWordPressRoute() !== $b->isWordPressRoute()) {
            return $a->isWordPressRoute() ? -1 : 1;
        }

        // Compare by explicit priority
        if ($a->getPriority() !== $b->getPriority()) {
            return $b->getPriority() <=> $a->getPriority();
        }

        // Compare by condition specificity
        return $a->getCondition()->isMoreSpecificThan($b->getCondition()) ? -1 : 1;
    }

    /**
     * Check if a template hierarchy should override a route
     *
     * Routes always take priority over templates.
     *
     * @param TemplateHierarchy $hierarchy The template hierarchy
     * @param Route $route The route to check against
     * @param array $context Additional context for comparison
     * @return bool Always false - routes have absolute priority
     */
    public function shouldTemplateOverrideRoute(TemplateHierarchy $hierarchy, Route $route, array $context = []): bool
    {
        // Routes always take priority over templates
        return false;
    }



    /**
     * Get priority level for different route types
     *
     * @param string $type The route type
     * @return int Priority level
     */
    public function getPriorityLevel(string $type): int
    {
        return self::PRIORITY_LEVELS[$type] ?? 0;
    }

    /**
     * Determine the effective priority for a route
     *
     * @param Route $route The route to evaluate
     * @return int Effective priority
     */
    public function getEffectivePriority(Route $route): int
    {
        $basePriority = $route->getPriority();

        // Add type-based priority bonus
        if ($route->isWordPressRoute()) {
            $specificityBonus = $route->getCondition()->getSpecificity();
            $basePriority += $specificityBonus;
        }

        return $basePriority;
    }

    /**
     * Order routes by priority
     *
     * @param Route[] $routes Array of routes to order
     * @return Route[] Routes ordered by priority (highest first)
     */
    public function orderByPriority(array $routes): array
    {
        usort($routes, [$this, 'compareRoutes']);
        return $routes;
    }

    /**
     * Get match type priority for ordering
     *
     * @param RouteMatch $match The route match
     * @return int Type priority
     */
    private function getMatchTypePriority(RouteMatch $match): int
    {
        return match ($match->getMatchedBy()) {
            'special_request' => self::PRIORITY_LEVELS['special_request'],
            'laravel_pattern' => self::PRIORITY_LEVELS['laravel_explicit'],
            'wordpress_condition' => $match->getRoute()->getCondition()->hasParameters()
                ? self::PRIORITY_LEVELS['wordpress_specific']
                : self::PRIORITY_LEVELS['wordpress_generic'],
            'template_hierarchy' => self::PRIORITY_LEVELS['template_hierarchy'],
            default => self::PRIORITY_LEVELS['fallback']
        };
    }
}
