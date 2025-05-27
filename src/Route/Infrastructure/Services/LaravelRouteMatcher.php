<?php

declare(strict_types=1);

namespace Pollora\Route\Infrastructure\Services;

use Pollora\Route\Domain\Contracts\RouteMatcherInterface;
use Pollora\Route\Domain\Contracts\RouteRegistryInterface;
use Pollora\Route\Domain\Models\Route;
use Pollora\Route\Domain\Models\RouteMatch;
use Pollora\Route\Domain\Services\RoutePriorityResolver;

/**
 * Laravel-based route matcher implementation
 * 
 * Implements route matching using Laravel's routing capabilities
 * while maintaining compatibility with WordPress conditions.
 */
final class LaravelRouteMatcher implements RouteMatcherInterface
{
    public function __construct(
        private readonly RouteRegistryInterface $registry,
        private readonly RoutePriorityResolver $priorityResolver
    ) {}

    /**
     * Match a URI and method against registered routes
     */
    public function match(string $uri, string $method, array $context = []): ?RouteMatch
    {
        // Get routes that support this HTTP method
        $candidateRoutes = $this->registry->getByMethod($method);
        
        if (empty($candidateRoutes)) {
            return null;
        }
        
        // Order routes by priority
        $prioritizedRoutes = $this->priorityResolver->orderByPriority($candidateRoutes);
        
        // Try to match each route
        $matches = [];
        foreach ($prioritizedRoutes as $route) {
            $match = $route->matches($context);
            if ($match->isMatched()) {
                $matches[] = $match;
            }
        }
        
        // Return the highest priority match
        return $this->priorityResolver->resolveHighestPriority($matches);
    }

    /**
     * Register a route for matching
     */
    public function register(Route $route): void
    {
        $this->registry->register($route);
    }

    /**
     * Get all routes ordered by priority
     */
    public function getPrioritizedRoutes(): array
    {
        return $this->registry->getByPriority(true);
    }

    /**
     * Check if a route is registered
     */
    public function hasRoute(string $routeId): bool
    {
        return $this->registry->has($routeId);
    }

    /**
     * Remove a route from the matcher
     */
    public function removeRoute(string $routeId): bool
    {
        return $this->registry->remove($routeId);
    }

    /**
     * Get routes by criteria
     */
    public function getRoutes(array $criteria = []): array
    {
        if (empty($criteria)) {
            return $this->registry->all();
        }
        
        return $this->registry->filter($criteria);
    }

    /**
     * Clear all registered routes
     */
    public function clear(): void
    {
        $this->registry->clear();
    }

    /**
     * Match routes with detailed information for debugging
     */
    public function matchWithDetails(string $uri, string $method, array $context = []): array
    {
        $candidateRoutes = $this->registry->getByMethod($method);
        $details = [
            'uri' => $uri,
            'method' => $method,
            'context' => $context,
            'candidate_routes' => count($candidateRoutes),
            'matches' => [],
            'best_match' => null,
        ];
        
        foreach ($candidateRoutes as $route) {
            $match = $route->matches($context);
            $matchDetails = [
                'route_id' => $route->getId(),
                'route_uri' => $route->getUri(),
                'is_matched' => $match->isMatched(),
                'priority' => $match->getPriority(),
                'matched_by' => $match->getMatchedBy(),
                'route_priority' => $route->getPriority(),
                'is_wordpress_route' => $route->isWordPressRoute(),
            ];
            
            $details['matches'][] = $matchDetails;
            
            if ($match->isMatched() && !$details['best_match']) {
                $details['best_match'] = $matchDetails;
            }
        }
        
        // Sort matches by priority
        usort($details['matches'], function ($a, $b) {
            return $b['priority'] <=> $a['priority'];
        });
        
        return $details;
    }

    /**
     * Get matching statistics
     */
    public function getMatchingStatistics(): array
    {
        $stats = $this->registry->getStatistics();
        
        $stats['priority_distribution'] = $this->calculatePriorityDistribution();
        $stats['condition_types'] = $this->calculateConditionTypeDistribution();
        $stats['middleware_usage'] = $this->calculateMiddlewareUsage();
        
        return $stats;
    }

    /**
     * Find routes by condition
     */
    public function findByCondition(string $condition, array $parameters = []): array
    {
        $allRoutes = $this->registry->all();
        $matchingRoutes = [];
        
        foreach ($allRoutes as $route) {
            if ($route->isWordPressRoute()) {
                $routeCondition = $route->getCondition();
                if ($routeCondition->getCondition() === $condition) {
                    if (empty($parameters) || $routeCondition->getParameters() === $parameters) {
                        $matchingRoutes[] = $route;
                    }
                }
            }
        }
        
        return $matchingRoutes;
    }

    /**
     * Test route matching without side effects
     */
    public function testMatch(Route $route, string $uri, string $method, array $context = []): RouteMatch
    {
        $testContext = array_merge($context, [
            'uri' => $uri,
            'method' => $method,
        ]);
        
        return $route->matches($testContext);
    }

    /**
     * Calculate priority distribution for statistics
     */
    private function calculatePriorityDistribution(): array
    {
        $routes = $this->registry->all();
        $distribution = [];
        
        foreach ($routes as $route) {
            $priority = $route->getPriority();
            $range = $this->getPriorityRange($priority);
            
            if (!isset($distribution[$range])) {
                $distribution[$range] = 0;
            }
            $distribution[$range]++;
        }
        
        return $distribution;
    }

    /**
     * Calculate condition type distribution
     */
    private function calculateConditionTypeDistribution(): array
    {
        $routes = $this->registry->all();
        $distribution = [];
        
        foreach ($routes as $route) {
            $type = $route->getCondition()->getType();
            
            if (!isset($distribution[$type])) {
                $distribution[$type] = 0;
            }
            $distribution[$type]++;
        }
        
        return $distribution;
    }

    /**
     * Calculate middleware usage statistics
     */
    private function calculateMiddlewareUsage(): array
    {
        $routes = $this->registry->all();
        $usage = [];
        
        foreach ($routes as $route) {
            foreach ($route->getMiddleware() as $middleware) {
                if (!isset($usage[$middleware])) {
                    $usage[$middleware] = 0;
                }
                $usage[$middleware]++;
            }
        }
        
        return $usage;
    }

    /**
     * Get priority range for grouping
     */
    private function getPriorityRange(int $priority): string
    {
        return match (true) {
            $priority >= 2000 => '2000+',
            $priority >= 1500 => '1500-1999',
            $priority >= 1000 => '1000-1499',
            $priority >= 500 => '500-999',
            $priority >= 100 => '100-499',
            default => '0-99'
        };
    }
}