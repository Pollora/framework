<?php

declare(strict_types=1);

namespace Pollora\Route\Infrastructure\Laravel;

use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Pollora\Route\Domain\Contracts\RouteRegistryInterface;
use Pollora\Route\Domain\Contracts\SpecialRequestHandlerInterface;
use Pollora\Route\Domain\Models\Route;
use Pollora\Route\Domain\Models\RouteCondition;
use Pollora\Route\Domain\Models\SpecialRequest;
use Pollora\Route\Domain\Services\SpecialRequestDetector;
use Pollora\Route\Domain\Services\WordPressContextBuilder;
use Pollora\Route\Domain\Services\TemplatePriorityComparator;
use Pollora\Route\Application\Services\BuildTemplateHierarchyService;
use Pollora\Route\Domain\Contracts\ConditionResolverInterface;

/**
 * Extended Laravel Router with WordPress integration
 *
 * Extends Laravel's native router to add WordPress-specific functionality
 * while maintaining full compatibility with existing Laravel features.
 */
final class ExtendedRouter extends Router
{
    private SpecialRequestDetector $specialRequestDetector;

    private SpecialRequestHandlerInterface $specialRequestHandler;

    private WordPressContextBuilder $contextBuilder;
    
    private ?RouteRegistryInterface $routeRegistry = null;

    private ?TemplatePriorityComparator $templateComparator = null;

    private ?BuildTemplateHierarchyService $hierarchyService = null;

    private ?ConditionResolverInterface $conditionResolver = null;

    /**
     * Override the matches method to handle WordPress special requests
     */
    public function matches(Request $request, bool $includingMethod = true)
    {
        // Check for WordPress special requests first
        $specialRequest = $this->detectSpecialRequest($request);
        if ($specialRequest) {
            $explicitRoute = $this->specialRequestHandler->findExplicitRoute($specialRequest);
            if ($explicitRoute) {
                // Convert domain route to Laravel route for compatibility
                return $this->convertToLaravelRoute($explicitRoute, $request);
            }
        }

        // Proceed with normal Laravel routing
        return parent::matches($request, $includingMethod);
    }

    /**
     * Set the special request detector
     */
    public function setSpecialRequestDetector(SpecialRequestDetector $detector): void
    {
        $this->specialRequestDetector = $detector;
    }

    /**
     * Set the special request handler
     */
    public function setSpecialRequestHandler(SpecialRequestHandlerInterface $handler): void
    {
        $this->specialRequestHandler = $handler;
    }

    /**
     * Set the WordPress context builder
     */
    public function setContextBuilder(WordPressContextBuilder $contextBuilder): void
    {
        $this->contextBuilder = $contextBuilder;
    }

    /**
     * Set the route registry for WordPress routes
     */
    public function setRouteRegistry(RouteRegistryInterface $registry): void
    {
        $this->routeRegistry = $registry;
    }

    /**
     * Set the template priority comparator
     */
    public function setTemplatePriorityComparator(TemplatePriorityComparator $comparator): void
    {
        $this->templateComparator = $comparator;
    }

    /**
     * Set the template hierarchy service
     */
    public function setTemplateHierarchyService(BuildTemplateHierarchyService $service): void
    {
        $this->hierarchyService = $service;
    }

    /**
     * Set the condition resolver
     */
    public function setConditionResolver(ConditionResolverInterface $resolver): void
    {
        $this->conditionResolver = $resolver;
    }

    /**
     * Add WordPress route macro functionality
     *
     * @param  array|string  $methods  HTTP methods
     * @param  string  $condition  WordPress condition
     * @param  mixed  ...$args  Condition parameters and action
     */
    public function addWordPressRoute(array|string $methods, string $condition, ...$args): ExtendedRoute
    {
        if (empty($args)) {
            throw new \InvalidArgumentException('WordPress route requires at least a condition and a callback.');
        }

        $action = $args[count($args) - 1];
        $conditionParams = count($args) > 1 ? array_slice($args, 0, -1) : [];

        // Generate unique URI for the route
        $uri = $this->generateUniqueUri($condition, $conditionParams);

        // Create the route using addRoute
        $route = $this->addRoute($methods, $uri, $action);

        // Configure as WordPress route if it's an ExtendedRoute
        if ($route instanceof ExtendedRoute) {
            $route->setIsWordPressRoute()
                ->setWordPressCondition($condition)
                ->setConditionParameters($conditionParams);

            // Ensure services are injected (they should already be from newRoute())
            if ($this->templateComparator) {
                $route->setTemplatePriorityComparator($this->templateComparator);
            }

            if ($this->hierarchyService) {
                $route->setTemplateHierarchyService($this->hierarchyService);
            }

            if ($this->conditionResolver) {
                $route->setConditionResolver($this->conditionResolver);
            }
        }

        // Also register the route in the domain registry for ResolveRouteService
        if ($this->routeRegistry) {
            $domainRoute = Route::wordpress(
                is_array($methods) ? $methods : [$methods],
                RouteCondition::fromWordPressTag($condition, $conditionParams),
                $action
            );
            $this->routeRegistry->register($domainRoute);
        }

        return $route;
    }

    /**
     * Create a new route instance
     */
    public function newRoute($methods, $uri, $action)
    {
        $route = (new ExtendedRoute($methods, $uri, $action))
            ->setRouter($this)
            ->setContainer($this->container);

        // Inject services if available
        if ($this->templateComparator) {
            $route->setTemplatePriorityComparator($this->templateComparator);
        }

        if ($this->hierarchyService) {
            $route->setTemplateHierarchyService($this->hierarchyService);
        }

        if ($this->conditionResolver) {
            $route->setConditionResolver($this->conditionResolver);
        }

        return $route;
    }

    /**
     * Build context from request for WordPress functions
     */
    private function buildContext(Request $request): array
    {
        $baseContext = [
            'request' => $request,
            'uri' => $request->getPathInfo(),
            'method' => $request->getMethod(),
            'headers' => $request->headers->all(),
            'parameters' => array_merge(
                $request->query->all(),
                $request->request->all()
            ),
        ];

        if (isset($this->contextBuilder)) {
            return $this->contextBuilder->buildContext($baseContext);
        }

        return $baseContext;
    }

    /**
     * Detect special WordPress request from the current request
     */
    private function detectSpecialRequest(Request $request): ?SpecialRequest
    {
        if (! isset($this->specialRequestDetector)) {
            return null;
        }

        $context = $this->buildContext($request);

        return $this->specialRequestDetector->detect($context);
    }

    /**
     * Convert domain route to Laravel route for compatibility
     */
    private function convertToLaravelRoute($domainRoute, Request $request): ?ExtendedRoute
    {
        if (! $domainRoute) {
            return null;
        }

        $laravelRoute = new ExtendedRoute(
            $domainRoute->getMethods(),
            $domainRoute->getUri(),
            $domainRoute->getAction()
        );

        $laravelRoute->setRouter($this)
            ->setContainer($this->container)
            ->setIsWordPressRoute();

        // Add WordPress middleware
        $middleware = $domainRoute->getMiddleware();
        if (! empty($middleware)) {
            $laravelRoute->middleware($middleware);
        }

        return $laravelRoute;
    }

    /**
     * Generate unique URI for WordPress route
     */
    private function generateUniqueUri(string $condition, array $parameters): string
    {
        $uri = $condition;

        if (! empty($parameters)) {
            $uri .= '_'.md5(serialize($parameters));
        }

        return $uri;
    }

    /**
     * Check if WordPress functions are available
     */
    private function wordPressIsAvailable(): bool
    {
        return function_exists('is_admin') && function_exists('get_option');
    }

    /**
     * Get WordPress route statistics for debugging
     */
    public function getWordPressRouteStats(): array
    {
        $stats = [
            'total_routes' => 0,
            'wordpress_routes' => 0,
            'laravel_routes' => 0,
            'special_request_routes' => 0,
        ];

        foreach ($this->getRoutes() as $route) {
            $stats['total_routes']++;

            if ($route instanceof ExtendedRoute && $route->isWordPressRoute()) {
                $stats['wordpress_routes']++;

                if ($route->isSpecialRequestRoute()) {
                    $stats['special_request_routes']++;
                }
            } else {
                $stats['laravel_routes']++;
            }
        }

        return $stats;
    }

    /**
     * Get all WordPress routes
     */
    public function getWordPressRoutes(): array
    {
        $wordpressRoutes = [];

        foreach ($this->getRoutes() as $route) {
            if ($route instanceof ExtendedRoute && $route->isWordPressRoute()) {
                $wordpressRoutes[] = $route;
            }
        }

        return $wordpressRoutes;
    }

    /**
     * Clear all WordPress routes (useful for testing)
     */
    public function clearWordPressRoutes(): void
    {
        $routeCollection = $this->getRoutes();
        $routesToKeep = [];

        foreach ($routeCollection as $route) {
            if (! ($route instanceof ExtendedRoute && $route->isWordPressRoute())) {
                $routesToKeep[] = $route;
            }
        }

        // Rebuild route collection with only non-WordPress routes
        $this->routes = new \Illuminate\Routing\RouteCollection;

        foreach ($routesToKeep as $route) {
            $this->routes->add($route);
        }
    }
}
