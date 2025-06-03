<?php

declare(strict_types=1);

namespace Tests\Unit\Route\Infrastructure\Services;

use Illuminate\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use PHPUnit\Framework\TestCase;
use Pollora\Route\Domain\Models\Route;
use Pollora\Route\Infrastructure\Services\ExtendedRouter;
use Pollora\Route\Infrastructure\Services\Resolvers\WordPressTypeResolver;
use Pollora\Route\Infrastructure\Services\WordPressConditionManager;

class ExtendedRouterTest extends TestCase
{
    private ExtendedRouter $router;

    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();

        $this->container = new Container;
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

        // Create dependencies with the mocked config
        $conditionManager = new WordPressConditionManager($this->container);
        $typeResolver = new WordPressTypeResolver;

        $this->router = new ExtendedRouter(
            $dispatcher,
            $this->container,
            $conditionManager,
            $typeResolver,
            null // no logger for tests
        );
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
        // Test that the addWordPressBindings method runs without error
        $closure = function (\WP_Post $post, \WP_Query $wp_query) {
            return [$post, $wp_query];
        };

        $route = new Route(['GET'], '/test', $closure);

        $result = $this->router->addWordPressBindings($route);

        // The method should return the same route instance
        $this->assertSame($route, $result);

        // Test with non-WordPress types (should not cause errors)
        $nonWpClosure = function (string $name, int $id) {
            return [$name, $id];
        };

        $nonWpRoute = new Route(['GET'], '/other', $nonWpClosure);
        $nonWpResult = $this->router->addWordPressBindings($nonWpRoute);

        $this->assertSame($nonWpRoute, $nonWpResult);
    }

    public function test_it_handles_missing_config_gracefully(): void
    {
        // Create router without config
        $container = new Container;
        $dispatcher = $this->createMock(Dispatcher::class);

        $conditionManager = new WordPressConditionManager($container);
        $typeResolver = new WordPressTypeResolver;

        $router = new ExtendedRouter($dispatcher, $container, $conditionManager, $typeResolver);

        $conditions = $router->getConditions();
        $this->assertIsArray($conditions);
        // Should have default conditions even without config
        $this->assertArrayHasKey('home', $conditions);
        $this->assertEquals('is_home', $conditions['home']);
    }
}
