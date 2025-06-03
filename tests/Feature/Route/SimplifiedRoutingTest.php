<?php

declare(strict_types=1);

namespace Tests\Feature\Route;

use Pollora\Route\Application\Services\ResolveRouteService;
use Pollora\Route\Domain\Contracts\RouteRegistryInterface;
use Pollora\Route\Domain\Models\RouteCondition;
use Pollora\Route\Domain\Services\RouteBuilder;
use Tests\TestCase;

/**
 * Integration tests for the simplified routing system.
 * 
 * @author Pollora Framework
 */
final class SimplifiedRoutingTest extends TestCase
{
    private RouteRegistryInterface $routeRegistry;
    private ResolveRouteService $routeResolver;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->routeRegistry = app(RouteRegistryInterface::class);
        $this->routeResolver = app(ResolveRouteService::class);
    }

    public function test_route_registration_order_is_preserved(): void
    {
        // Register routes in specific order
        $route1 = RouteBuilder::create()
            ->id('route-1')
            ->get()
            ->uri('/test')
            ->action(fn() => 'first')
            ->build();

        $route2 = RouteBuilder::create()
            ->id('route-2')
            ->get()
            ->uri('/test/*')
            ->action(fn() => 'second')
            ->build();

        $this->routeRegistry->register($route1);
        $this->routeRegistry->register($route2);

        // Get registered routes
        $routes = $this->routeRegistry->getRoutes();

        $this->assertCount(2, $routes);
        $this->assertEquals('route-1', $routes[0]->getId());
        $this->assertEquals('route-2', $routes[1]->getId());
    }

    public function test_route_matching_respects_declaration_order(): void
    {
        // Clear any existing routes
        $this->routeRegistry->clear();

        // Register more specific route first
        $specificRoute = RouteBuilder::create()
            ->id('specific-route')
            ->get()
            ->uri('/posts/featured')
            ->action(fn() => 'featured posts')
            ->build();

        // Register less specific route second
        $genericRoute = RouteBuilder::create()
            ->id('generic-route')
            ->get()
            ->uri('/posts/*')
            ->action(fn() => 'any post')
            ->build();

        $this->routeRegistry->register($specificRoute);
        $this->routeRegistry->register($genericRoute);

        // Test that specific route matches first
        $resolution = $this->routeResolver->execute('/posts/featured', 'GET');

        $this->assertTrue($resolution->isRoute());
        $this->assertNotNull($resolution->getRouteMatch());
        $this->assertEquals('specific-route', $resolution->getRouteMatch()->getRoute()->getId());
    }

    public function test_wordpress_condition_route_registration(): void
    {
        $conditions = config('wordpress.conditions', []);
        
        $route = RouteBuilder::create($conditions)
            ->id('single-post-route')
            ->get()
            ->wp('single') // Uses condition alias
            ->action(fn() => 'single post view')
            ->build();

        $this->routeRegistry->register($route);

        $registeredRoute = $this->routeRegistry->getRoute('single-post-route');
        
        $this->assertNotNull($registeredRoute);
        $this->assertTrue($registeredRoute->isWordPressRoute());
        $this->assertEquals('wordpress', $registeredRoute->getCondition()->getType());
        $this->assertEquals('is_single', $registeredRoute->getCondition()->getCondition());
    }

    public function test_route_resolution_falls_back_to_templates(): void
    {
        // Clear routes to ensure no matches
        $this->routeRegistry->clear();

        // Mock WordPress context that would normally be set by WordPress
        $context = [
            'is_single' => true,
            'post_type' => 'post',
            'post_name' => 'hello-world'
        ];

        $resolution = $this->routeResolver->execute('/hello-world', 'GET', $context);

        // Since no routes match, should fall back to template resolution
        $this->assertTrue($resolution->isTemplate() || $resolution->isNotFound());
    }

    public function test_route_builder_with_middleware(): void
    {
        $route = RouteBuilder::create()
            ->id('protected-route')
            ->get()
            ->uri('/admin/*')
            ->middleware('auth', 'admin')
            ->action(fn() => 'admin panel')
            ->build();

        $this->assertEquals(['auth', 'admin'], $route->getMiddleware());
    }

    public function test_route_builder_fluent_interface(): void
    {
        $route = RouteBuilder::create()
            ->id('complex-route')
            ->methods('GET', 'POST')
            ->uri('/api/posts/{id}')
            ->middleware('api', 'throttle')
            ->action('PostController@show')
            ->build();

        $this->assertEquals('complex-route', $route->getId());
        $this->assertEquals(['GET', 'POST'], $route->getMethods());
        $this->assertEquals('uri', $route->getCondition()->getType());
        $this->assertEquals('/api/posts/{id}', $route->getCondition()->getCondition());
        $this->assertEquals(['api', 'throttle'], $route->getMiddleware());
        $this->assertEquals('PostController@show', $route->getAction());
    }
}