<?php

declare(strict_types=1);

namespace Pollora\Route\Application\Services;

use Pollora\Route\Domain\Contracts\RouteMatcherInterface;
use Pollora\Route\Domain\Contracts\SpecialRequestHandlerInterface;
use Pollora\Route\Domain\Contracts\TemplateResolverInterface;
use Pollora\Route\Domain\Models\RouteMatch;
use Pollora\Route\Domain\Services\RoutePriorityResolver;
use Pollora\Route\Domain\Services\SpecialRequestDetector;
use Pollora\Route\Domain\Services\WordPressContextBuilder;

/**
 * Service for resolving the best route for a request
 *
 * Orchestrates the route resolution process following the priority rules:
 * 1. Special WordPress requests (if explicit route exists)
 * 2. Laravel routes
 * 3. WordPress routes with specific conditions
 * 4. WordPress routes with generic conditions
 * 5. Template hierarchy (only if no route matches)
 * 6. Special WordPress requests (default handlers)
 * 7. 404 fallback
 */
final class ResolveRouteService
{
    public function __construct(
        private readonly RouteMatcherInterface $routeMatcher,
        private readonly TemplateResolverInterface $templateResolver,
        private readonly SpecialRequestHandlerInterface $specialRequestHandler,
        private readonly SpecialRequestDetector $specialRequestDetector,
        private readonly RoutePriorityResolver $priorityResolver,
        private readonly WordPressContextBuilder $contextBuilder,
        private readonly array $config = []
    ) {}

    /**
     * Resolve the best route for the given request
     *
     * @param  string  $uri  Request URI
     * @param  string  $method  HTTP method
     * @param  array  $context  Additional request context
     * @return RouteResolution The resolved route information
     */
    public function execute(string $uri, string $method, array $context = []): RouteResolution
    {
        // Build comprehensive context
        $fullContext = $this->buildContext($uri, $method, $context);

        // Step 1: Check for special WordPress requests with explicit routes
        $specialRequest = $this->specialRequestDetector->detect($fullContext);
        if ($specialRequest) {
            $explicitRoute = $this->specialRequestHandler->findExplicitRoute($specialRequest);
            if ($explicitRoute) {
                $match = RouteMatch::fromSpecialRequest($explicitRoute, [], $specialRequest->getPriority());

                return RouteResolution::fromRouteMatch($match, 'special_explicit');
            }
        }

        // Step 2: Try standard route matching (Laravel + WordPress)
        $routeMatch = $this->routeMatcher->match($uri, $method, $fullContext);

        if ($routeMatch && $routeMatch->isMatched()) {
            // Routes always take priority over templates
            return RouteResolution::fromRouteMatch($routeMatch, 'route_match');
        }

        // Step 3: Try template hierarchy resolution
        $templateHierarchy = $this->templateResolver->resolveHierarchy($fullContext);
        $templatePath = $this->templateResolver->findTemplate($templateHierarchy);
        if ($templatePath) {
            return RouteResolution::fromTemplate($templateHierarchy, $templatePath, 'template_hierarchy');
        }

        // Step 4: Handle special requests with default WordPress behavior
        if ($specialRequest && $this->specialRequestHandler->canHandle($specialRequest)) {
            $response = $this->specialRequestHandler->handle($specialRequest);

            return RouteResolution::fromSpecialResponse($specialRequest, $response, 'special_default');
        }

        // Step 5: 404 fallback
        return RouteResolution::notFound();
    }

    /**
     * Check if a route exists for the given parameters
     *
     * @param  string  $uri  Request URI
     * @param  string  $method  HTTP method
     * @param  array  $context  Additional context
     * @return bool True if a route exists
     */
    public function routeExists(string $uri, string $method, array $context = []): bool
    {
        $resolution = $this->execute($uri, $method, $context);

        return $resolution->isResolved();
    }

    /**
     * Get all possible routes for debugging purposes
     *
     * @param  string  $uri  Request URI
     * @param  string  $method  HTTP method
     * @param  array  $context  Additional context
     * @return array Debug information about route resolution
     */
    public function getDebugInfo(string $uri, string $method, array $context = []): array
    {
        $fullContext = $this->buildContext($uri, $method, $context);

        return [
            'uri' => $uri,
            'method' => $method,
            'context' => $fullContext,
            'special_request' => $this->specialRequestDetector->detect($fullContext)?->toArray(),
            'route_match' => $this->routeMatcher->match($uri, $method, $fullContext)?->toArray(),
            'template_hierarchy' => $this->templateResolver->resolveHierarchy($fullContext)->toArray(),
            'available_routes' => array_map(
                fn ($route) => [
                    'id' => $route->getId(),
                    'uri' => $route->getUri(),
                    'methods' => $route->getMethods(),
                    'is_wordpress' => $route->isWordPressRoute(),
                    'priority' => $route->getPriority(),
                ],
                $this->routeMatcher->getPrioritizedRoutes()
            ),
        ];
    }

    /**
     * Resolve multiple routes for batch processing
     *
     * @param  array  $requests  Array of [uri, method, context] arrays
     * @return array Array of RouteResolution objects
     */
    public function resolveMultiple(array $requests): array
    {
        $results = [];

        foreach ($requests as $request) {
            $uri = $request['uri'] ?? $request[0] ?? '';
            $method = $request['method'] ?? $request[1] ?? 'GET';
            $context = $request['context'] ?? $request[2] ?? [];

            $results[] = $this->execute($uri, $method, $context);
        }

        return $results;
    }


    /**
     * Build comprehensive context for route resolution
     *
     * @param  string  $uri  Request URI
     * @param  string  $method  HTTP method
     * @param  array  $userContext  User-provided context
     * @return array Complete context array
     */
    private function buildContext(string $uri, string $method, array $userContext): array
    {
        // Use the injected context builder for WordPress context
        $baseContext = $this->contextBuilder->buildContext([
            'uri' => $uri,
            'path' => parse_url($uri, PHP_URL_PATH) ?: $uri,
            'method' => strtoupper($method),
            'query' => parse_url($uri, PHP_URL_QUERY),
            'timestamp' => time(),
        ]);

        // Add request information if available
        if (function_exists('request') && request()) {
            $request = request();
            $baseContext['request'] = $request;
            $baseContext['headers'] = $request->headers->all();
            $baseContext['parameters'] = array_merge(
                $request->query->all(),
                $request->request->all()
            );
        }

        // Merge with user-provided context
        return array_merge($baseContext, $userContext);
    }
}
