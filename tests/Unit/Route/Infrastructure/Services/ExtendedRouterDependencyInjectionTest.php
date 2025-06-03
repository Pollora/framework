<?php

declare(strict_types=1);

namespace Tests\Unit\Route\Infrastructure\Services;

use Illuminate\Container\Container;
use Illuminate\Events\Dispatcher;
use Pollora\Route\Infrastructure\Services\ExtendedRouter;
use Tests\TestCase;

/**
 * @covers \Pollora\Route\Infrastructure\Services\ExtendedRouter
 */
class ExtendedRouterDependencyInjectionTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->container = new Container();
        $dispatcher = new Dispatcher($this->container);
        new ExtendedRouter($dispatcher, $this->container);
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
}