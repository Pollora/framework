<?php

declare(strict_types=1);

namespace Tests\Unit\Route\Infrastructure\Services;

use Illuminate\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\TestCase;
use Pollora\Route\Domain\Models\Route;
use Pollora\Route\Infrastructure\Services\ExtendedRouter;

class ExtendedRouterTest extends TestCase
{
    private ExtendedRouter $router;
    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->container = new Container();
        $dispatcher = $this->createMock(Dispatcher::class);
        
        // Mock config
        $config = $this->createMock(\Illuminate\Config\Repository::class);
        $config->method('get')
            ->with('wordpress.routing.conditions', $this->anything())
            ->willReturn([
                'single' => 'is_single',
                'page' => 'is_page',
                'category' => 'is_category',
            ]);
        
        $this->container->instance('config', $config);
        
        $this->router = new ExtendedRouter($dispatcher, $this->container);
    }

    public function test_it_creates_route_objects_of_correct_type(): void
    {
        $route = $this->router->get('/test', function () {
            return 'test';
        });
        
        $this->assertInstanceOf(Route::class, $route);
    }

    public function test_it_loads_wordpress_conditions_from_config(): void
    {
        $conditions = $this->router->getConditions();
        
        $this->assertArrayHasKey('single', $conditions);
        $this->assertEquals('is_single', $conditions['single']);
        $this->assertArrayHasKey('page', $conditions);
        $this->assertEquals('is_page', $conditions['page']);
        $this->assertArrayHasKey('category', $conditions);
        $this->assertEquals('is_category', $conditions['category']);
    }

    public function test_it_resolves_condition_aliases(): void
    {
        $this->assertEquals('is_single', $this->router->resolveCondition('single'));
        $this->assertEquals('is_page', $this->router->resolveCondition('page'));
        
        // Non-aliased conditions should return as-is
        $this->assertEquals('is_custom', $this->router->resolveCondition('is_custom'));
    }

    public function test_it_adds_wordpress_bindings_to_route(): void
    {
        // Mock WordPress globals
        global $post, $wp_query;
        $post = (object) ['ID' => 123, 'post_title' => 'Test Post'];
        $wp_query = (object) ['is_main_query' => true];
        
        $route = new Route(['GET'], '/test', function () {});
        
        $result = $this->router->addWordPressBindings($route);
        
        $this->assertSame($route, $result);
        $this->assertEquals($post, $route->parameter('post'));
        $this->assertEquals($wp_query, $route->parameter('wp_query'));
    }

    public function test_it_handles_missing_config_gracefully(): void
    {
        // Create router without config
        $container = new Container();
        $dispatcher = $this->createMock(Dispatcher::class);
        
        $router = new ExtendedRouter($dispatcher, $container);
        
        $conditions = $router->getConditions();
        $this->assertIsArray($conditions);
    }
}