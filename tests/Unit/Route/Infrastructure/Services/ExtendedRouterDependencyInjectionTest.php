<?php

declare(strict_types=1);

namespace Tests\Unit\Route\Infrastructure\Services;

use Illuminate\Container\Container;
use Illuminate\Events\Dispatcher;
use PHPUnit\Framework\Attributes\CoversClass;
use Pollora\Route\Infrastructure\Services\ExtendedRouter;
use Pollora\Route\Infrastructure\Services\Resolvers\WordPressTypeResolver;
use Pollora\Route\Infrastructure\Services\WordPressConditionManager;
use Tests\TestCase;

#[CoversClass(\Pollora\Route\Infrastructure\Services\ExtendedRouter::class)]
class ExtendedRouterDependencyInjectionTest extends TestCase
{
    private Container $container;

    private ExtendedRouter $router;

    protected function setUp(): void
    {
        parent::setUp();

        $this->container = new Container;
        $dispatcher = new Dispatcher($this->container);

        // Register dependencies
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

    public function test_wordpress_types_are_registered_in_container(): void
    {
        $expectedTypes = ['WP_Post', 'WP_Term', 'WP_User', 'WP_Query', 'WP'];

        foreach ($expectedTypes as $type) {
            $this->assertTrue(
                $this->container->bound($type),
                "WordPress type {$type} should be bound in the container"
            );
        }
    }

    public function test_router_can_resolve_conditions(): void
    {
        $conditions = $this->router->getConditions();

        $this->assertIsArray($conditions);
        $this->assertArrayHasKey('home', $conditions);
        $this->assertEquals('is_home', $conditions['home']);

        $this->assertEquals('is_single', $this->router->resolveCondition('single'));
        $this->assertEquals('unknown', $this->router->resolveCondition('unknown'));
    }
}
