<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Pollora\Route\Domain\Models\Route;

describe('Route', function (): void {
    beforeEach(function (): void {
        $this->route = new Route(['GET'], '/test', fn (): string => 'test');
    });

    it('can set and check WordPress route status', function (): void {
        expect($this->route->isWordPressRoute())->toBeFalse();

        $this->route->setIsWordPressRoute(true);

        expect($this->route->isWordPressRoute())->toBeTrue();
    });

    it('can set and get condition', function (): void {
        expect($this->route->hasCondition())->toBeFalse();
        expect($this->route->getCondition())->toBeEmpty();

        $this->route->setCondition('is_single');

        expect($this->route->hasCondition())->toBeTrue();
        expect($this->route->getCondition())->toBe('is_single');
    });

    it('can set and get condition parameters', function (): void {
        expect($this->route->getConditionParameters())->toBeEmpty();

        $parameters = ['product', 123];
        $this->route->setConditionParameters($parameters);

        expect($this->route->getConditionParameters())->toBe($parameters);
    });

    it('matches WordPress condition when function exists', function (): void {
        if (! function_exists('test_wp_function')) {
            eval('function test_wp_function($param = null) { return $param === "test"; }');
        }

        $this->route->setIsWordPressRoute(true);
        $this->route->setCondition('test_wp_function');
        $this->route->setConditionParameters(['test']);

        $request = Request::create('/test');

        expect($this->route->matches($request))->toBeTrue();
    });

    it('does not match when WordPress function returns false', function (): void {
        if (! function_exists('test_wp_function_false')) {
            eval('function test_wp_function_false() { return false; }');
        }

        $this->route->setIsWordPressRoute(true);
        $this->route->setCondition('test_wp_function_false');

        $request = Request::create('/test');

        expect($this->route->matches($request))->toBeFalse();
    });

    it('falls back to Laravel matching for non-WordPress routes', function (): void {
        $request = Request::create('/test', 'GET');
        expect($this->route->matches($request))->toBeTrue();

        $wrongRequest = Request::create('/different', 'GET');
        expect($this->route->matches($wrongRequest))->toBeFalse();
    });

    it('returns false when WordPress function does not exist', function (): void {
        $this->route->setIsWordPressRoute(true);
        $this->route->setCondition('non_existent_function');

        $request = Request::create('/test');

        expect($this->route->matches($request))->toBeFalse();
    });

    it('chaining methods return route instance', function (): void {
        $result = $this->route
            ->setIsWordPressRoute(true)
            ->setCondition('is_single')
            ->setConditionParameters(['test']);

        expect($result)->toBeInstanceOf(Route::class);
        expect($result)->toBe($this->route);
    });
});
