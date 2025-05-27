<?php

declare(strict_types=1);

namespace Tests\Unit\Route\Domain\Models;

use PHPUnit\Framework\TestCase;
use Pollora\Route\Domain\Models\Route;
use Pollora\Route\Domain\Models\RouteCondition;

/**
 * @covers \Pollora\Route\Domain\Models\Route
 */
class RouteTest extends TestCase
{
    public function testLaravelRoute(): void
    {
        $route = Route::laravel('/users/{id}', ['GET'], function () {
            return 'users';
        });

        $this->assertEquals('/users/{id}', $route->getUri());
        $this->assertEquals(['GET'], $route->getMethods());
        $this->assertFalse($route->isWordPressRoute());
        $this->assertIsCallable($route->getAction());
    }

    public function testWordPressRoute(): void
    {
        $condition = RouteCondition::fromWordPressTag('is_page', ['about']);
        $route = Route::wordpress(['GET'], $condition, function () {
            return 'page';
        });

        $this->assertTrue($route->isWordPressRoute());
        $this->assertEquals(['GET'], $route->getMethods());
        $this->assertSame($condition, $route->getCondition());
        $this->assertIsCallable($route->getAction());
    }

    public function testFromWordPressTag(): void
    {
        $route = Route::fromWordPressTag(
            ['GET', 'POST'],
            'is_page',
            ['about'],
            function () {
                return 'about';
            }
        );

        $this->assertTrue($route->isWordPressRoute());
        $this->assertEquals(['GET', 'POST'], $route->getMethods());
        $this->assertEquals('is_page', $route->getCondition()->getCondition());
        $this->assertEquals(['about'], $route->getCondition()->getParameters());
    }

    public function testPriorityCalculation(): void
    {
        // WordPress route with specific parameters should have higher priority
        $specific = Route::fromWordPressTag(
            ['GET'],
            'is_page',
            ['about'],
            function () { return 'specific'; }
        );

        $generic = Route::fromWordPressTag(
            ['GET'],
            'is_page',
            [],
            function () { return 'generic'; }
        );

        $this->assertGreaterThan($generic->getPriority(), $specific->getPriority());
    }

    public function testPriorityComparison(): void
    {
        $wpRoute = Route::fromWordPressTag(
            ['GET'],
            'is_page',
            [],
            function () { return 'wp'; }
        );

        $laravelRoute = Route::laravel('/test', ['GET'], function () {
            return 'laravel';
        });

        $this->assertTrue($wpRoute->hasPriorityOver($laravelRoute));
        $this->assertFalse($laravelRoute->hasPriorityOver($wpRoute));
    }

    public function testWithMiddleware(): void
    {
        $route = Route::laravel('/test', ['GET'], function () {
            return 'test';
        });

        $newRoute = $route->withMiddleware(['auth', 'web']);

        $this->assertEquals(['auth', 'web'], $newRoute->getMiddleware());
        $this->assertEmpty($route->getMiddleware()); // Original unchanged
    }

    public function testWithMetadata(): void
    {
        $route = Route::laravel('/test', ['GET'], function () {
            return 'test';
        });

        $newRoute = $route->withMetadata(['key' => 'value']);

        $this->assertEquals('value', $newRoute->getMetadata('key'));
        $this->assertNull($route->getMetadata('key')); // Original unchanged
    }

    public function testGetId(): void
    {
        $route1 = Route::laravel('/test', ['GET'], function () {
            return 'test';
        });

        $route2 = Route::laravel('/test', ['GET'], function () {
            return 'test';
        });

        // Same routes should have same ID
        $this->assertEquals($route1->getId(), $route2->getId());

        $route3 = Route::laravel('/different', ['GET'], function () {
            return 'different';
        });

        // Different routes should have different IDs
        $this->assertNotEquals($route1->getId(), $route3->getId());
    }

    public function testMatches(): void
    {
        $route = Route::laravel('/users/{id}', ['GET'], function () {
            return 'test';
        });

        $context = [
            'method' => 'GET',
            'uri' => '/users/123',
        ];

        $match = $route->matches($context);
        $this->assertTrue($match->isMatched());
    }

    public function testMatchesFailsForWrongMethod(): void
    {
        $route = Route::laravel('/test', ['POST'], function () {
            return 'test';
        });

        $context = [
            'method' => 'GET',
            'uri' => '/test',
        ];

        $match = $route->matches($context);
        $this->assertFalse($match->isMatched());
    }

    public function testWordPressRouteWithExplicitPriority(): void
    {
        $condition = RouteCondition::fromWordPressTag('is_page');
        $route = Route::wordpress(['GET'], $condition, function () {
            return 'test';
        }, 1000);

        $this->assertEquals(1000, $route->getPriority());
    }

    public function testConditionSpecificityPriority(): void
    {
        $pageSpecific = Route::fromWordPressTag(
            ['GET'],
            'is_page',
            ['about'],
            function () { return 'page-about'; }
        );

        $pageGeneric = Route::fromWordPressTag(
            ['GET'],
            'is_page',
            [],
            function () { return 'page'; }
        );

        $archive = Route::fromWordPressTag(
            ['GET'],
            'is_archive',
            [],
            function () { return 'archive'; }
        );

        // More specific conditions should have higher priority
        $this->assertTrue($pageSpecific->hasPriorityOver($pageGeneric));
        $this->assertTrue($pageGeneric->hasPriorityOver($archive));
    }
}