<?php

declare(strict_types=1);

namespace Tests\Unit\Route\Domain\Models;

use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;
use Pollora\Route\Domain\Models\Route;

class RouteTest extends TestCase
{
    private Route $route;

    protected function setUp(): void
    {
        parent::setUp();

        $this->route = new Route(['GET'], '/test', function () {
            return 'test';
        });
    }

    public function test_it_can_set_and_check_wordpress_route_status(): void
    {
        $this->assertFalse($this->route->isWordPressRoute());

        $this->route->setIsWordPressRoute(true);

        $this->assertTrue($this->route->isWordPressRoute());
    }

    public function test_it_can_set_and_get_condition(): void
    {
        $this->assertFalse($this->route->hasCondition());
        $this->assertEmpty($this->route->getCondition());

        $this->route->setCondition('is_single');

        $this->assertTrue($this->route->hasCondition());
        $this->assertEquals('is_single', $this->route->getCondition());
    }

    public function test_it_can_set_and_get_condition_parameters(): void
    {
        $this->assertEmpty($this->route->getConditionParameters());

        $parameters = ['product', 123];
        $this->route->setConditionParameters($parameters);

        $this->assertEquals($parameters, $this->route->getConditionParameters());
    }

    public function test_it_matches_wordpress_condition_when_function_exists(): void
    {
        // Mock a WordPress function
        if (! function_exists('test_wp_function')) {
            eval('function test_wp_function($param = null) { return $param === "test"; }');
        }

        $this->route->setIsWordPressRoute(true);
        $this->route->setCondition('test_wp_function');
        $this->route->setConditionParameters(['test']);

        $request = Request::create('/test');

        $this->assertTrue($this->route->matches($request));
    }

    public function test_it_does_not_match_when_wordpress_function_returns_false(): void
    {
        // Mock a WordPress function that returns false
        if (! function_exists('test_wp_function_false')) {
            eval('function test_wp_function_false() { return false; }');
        }

        $this->route->setIsWordPressRoute(true);
        $this->route->setCondition('test_wp_function_false');

        $request = Request::create('/test');

        $this->assertFalse($this->route->matches($request));
    }

    public function test_it_falls_back_to_laravel_matching_for_non_wordpress_routes(): void
    {
        $request = Request::create('/test', 'GET');

        // This should use Laravel's default matching behavior
        $this->assertTrue($this->route->matches($request));

        // Different URI should not match
        $wrongRequest = Request::create('/different', 'GET');
        $this->assertFalse($this->route->matches($wrongRequest));
    }

    public function test_it_returns_false_when_wordpress_function_does_not_exist(): void
    {
        $this->route->setIsWordPressRoute(true);
        $this->route->setCondition('non_existent_function');

        $request = Request::create('/test');

        $this->assertFalse($this->route->matches($request));
    }

    public function test_chaining_methods_return_route_instance(): void
    {
        $result = $this->route
            ->setIsWordPressRoute(true)
            ->setCondition('is_single')
            ->setConditionParameters(['test']);

        $this->assertInstanceOf(Route::class, $result);
        $this->assertSame($this->route, $result);
    }
}
