<?php

declare(strict_types=1);

namespace Tests\Unit\Route\Domain\Models;

use PHPUnit\Framework\TestCase;
use Pollora\Route\Domain\Models\Route;
use Pollora\Route\Domain\Models\RouteCondition;
use Pollora\Route\Domain\Models\RouteMatch;

/**
 * @covers \Pollora\Route\Domain\Models\RouteMatch
 */
class RouteMatchTest extends TestCase
{
    public function testSuccess(): void
    {
        $route = Route::wordpress(
            ['GET'],
            RouteCondition::fromWordPressTag('is_page', ['about']),
            function () { return 'test'; }
        );
        
        $parameters = ['id' => 1, 'slug' => 'test'];
        $match = RouteMatch::success($route, $parameters, 100, 'test_matcher');

        $this->assertSame($route, $match->getRoute());
        $this->assertEquals($parameters, $match->getParameters());
        $this->assertTrue($match->isMatched());
        $this->assertEquals(100, $match->getPriority());
        $this->assertEquals('test_matcher', $match->getMatchedBy());
    }

    public function testFailed(): void
    {
        $match = RouteMatch::failed();

        $this->assertFalse($match->isMatched());
        $this->assertNull($match->getRoute());
    }

    public function testFromTemplateHierarchy(): void
    {
        $route = Route::wordpress(
            ['GET'],
            RouteCondition::fromWordPressTag('is_page'),
            function () { return 'test'; }
        );
        
        $match = RouteMatch::fromTemplateHierarchy($route, [], 200);

        $this->assertTrue($match->isMatched());
        $this->assertEquals(200, $match->getPriority());
        $this->assertEquals('template_hierarchy', $match->getMatchedBy());
        $this->assertTrue($match->isFromTemplateHierarchy());
    }

    public function testFromSpecialRequest(): void
    {
        $route = Route::wordpress(
            ['GET'],
            RouteCondition::fromWordPressTag('is_robots'),
            function () { return 'robots'; }
        );
        
        $match = RouteMatch::fromSpecialRequest($route, [], 1000);

        $this->assertTrue($match->isMatched());
        $this->assertEquals(1000, $match->getPriority());
        $this->assertEquals('special_request', $match->getMatchedBy());
        $this->assertTrue($match->isFromSpecialRequest());
    }

    public function testHasPriorityOverWithDifferentPriorities(): void
    {
        $route1 = Route::wordpress(
            ['GET'],
            RouteCondition::fromWordPressTag('is_page'),
            function () { return 'test1'; }
        );
        
        $route2 = Route::wordpress(
            ['GET'],
            RouteCondition::fromWordPressTag('is_archive'),
            function () { return 'test2'; }
        );

        $match1 = RouteMatch::success($route1, [], 800);
        $match2 = RouteMatch::success($route2, [], 400);

        $this->assertTrue($match1->hasPriorityOver($match2));
        $this->assertFalse($match2->hasPriorityOver($match1));
    }

    public function testHasPriorityOverWithSamePriorityUsesRouteSpecificity(): void
    {
        $route1 = Route::wordpress(
            ['GET'],
            RouteCondition::fromWordPressTag('is_page', ['about']), // More specific
            function () { return 'test1'; }
        );
        
        $route2 = Route::wordpress(
            ['GET'],
            RouteCondition::fromWordPressTag('is_page'), // Less specific
            function () { return 'test2'; }
        );

        $match1 = RouteMatch::success($route1, [], 500);
        $match2 = RouteMatch::success($route2, [], 500);

        $this->assertTrue($match1->hasPriorityOver($match2));
    }

    public function testGetParameter(): void
    {
        $route = Route::wordpress(
            ['GET'],
            RouteCondition::fromWordPressTag('is_page'),
            function () { return 'test'; }
        );
        
        $parameters = ['id' => 123, 'slug' => 'about'];
        $match = RouteMatch::success($route, $parameters);

        $this->assertEquals(123, $match->getParameter('id'));
        $this->assertEquals('about', $match->getParameter('slug'));
        $this->assertEquals('default', $match->getParameter('missing', 'default'));
        $this->assertNull($match->getParameter('missing'));
    }

    public function testHasParameter(): void
    {
        $route = Route::wordpress(
            ['GET'],
            RouteCondition::fromWordPressTag('is_page'),
            function () { return 'test'; }
        );
        
        $parameters = ['id' => 123];
        $match = RouteMatch::success($route, $parameters);

        $this->assertTrue($match->hasParameter('id'));
        $this->assertFalse($match->hasParameter('slug'));
    }

    public function testIsWordPressRoute(): void
    {
        $wpRoute = Route::wordpress(
            ['GET'],
            RouteCondition::fromWordPressTag('is_page'),
            function () { return 'wp'; }
        );
        
        $laravelRoute = Route::laravel(
            '/users/{id}',
            ['GET'],
            function () { return 'laravel'; }
        );

        $wpMatch = RouteMatch::success($wpRoute);
        $laravelMatch = RouteMatch::success($laravelRoute);

        $this->assertTrue($wpMatch->isWordPressRoute());
        $this->assertFalse($laravelMatch->isWordPressRoute());
    }

    public function testWithParameters(): void
    {
        $route = Route::wordpress(
            ['GET'],
            RouteCondition::fromWordPressTag('is_page'),
            function () { return 'test'; }
        );
        
        $match = RouteMatch::success($route, ['id' => 1]);
        $newMatch = $match->withParameters(['slug' => 'about']);

        $this->assertEquals(['id' => 1, 'slug' => 'about'], $newMatch->getParameters());
        $this->assertEquals(['id' => 1], $match->getParameters()); // Original unchanged
    }

    public function testWithPriority(): void
    {
        $route = Route::wordpress(
            ['GET'],
            RouteCondition::fromWordPressTag('is_page'),
            function () { return 'test'; }
        );
        
        $match = RouteMatch::success($route, [], 100);
        $newMatch = $match->withPriority(200);

        $this->assertEquals(200, $newMatch->getPriority());
        $this->assertEquals(100, $match->getPriority()); // Original unchanged
    }

    public function testToArray(): void
    {
        $route = Route::wordpress(
            ['GET'],
            RouteCondition::fromWordPressTag('is_page', ['about']),
            function () { return 'test'; }
        );
        
        $match = RouteMatch::success($route, ['id' => 123], 500, 'test_matcher');
        $array = $match->toArray();

        $this->assertEquals($route->getId(), $array['route_id']);
        $this->assertEquals(['id' => 123], $array['parameters']);
        $this->assertTrue($array['is_matched']);
        $this->assertEquals(500, $array['priority']);
        $this->assertEquals('test_matcher', $array['matched_by']);
        $this->assertTrue($array['is_wordpress_route']);
    }
}