<?php

declare(strict_types=1);

use Pollora\Attributes\Attributable;
use Pollora\Attributes\AttributeProcessor;
use Pollora\Attributes\WpRestRoute;
use Pollora\Attributes\WpRestRoute\Method;

// Test class implementing the Attributable interface
#[WpRestRoute('api/v1', '/test', 'TestPermission')]
class TestController implements Attributable
{
    public ?string $namespace = null;

    public ?string $route = null;

    public ?string $classPermission = null;

    #[Method(['GET', 'POST'])]
    public function testMethod(string $param1): string
    {
        return "Test {$param1}";
    }

    #[Method('GET', 'CustomPermission')]
    public function testMethodWithCustomPermission(): string
    {
        return 'Test with custom permission';
    }
}

// Mock register_rest_route if not already defined
if (! function_exists('register_rest_route')) {
    function register_rest_route($namespace, $route, $args)
    {
        // Mock: do nothing
        return true;
    }
}

/**
 * WpRestRoute attribute tests
 */
describe('WpRestRoute attribute', function () {
    test('sets correct properties on class', function () {
        $controller = new TestController;
        $processor = new AttributeProcessor;
        $processor->process($controller);

        expect($controller->namespace)->toBe('api/v1')
            ->and($controller->route)->toBe('/test')
            ->and($controller->classPermission)->toBe('TestPermission');
    });
});

/**
 * Method attribute tests
 */
describe('Method attribute', function () {
    test('validates HTTP methods correctly', function () {
        expect(fn () => new Method(['INVALID']))
            ->toThrow(InvalidArgumentException::class);

        expect(fn () => new Method(['GET', 'POST']))
            ->not->toThrow(InvalidArgumentException::class);
    });

    test('correctly handles multiple HTTP methods', function () {
        $method = new Method(['GET', 'POST']);

        expect($method->getMethods())
            ->toBe(['GET', 'POST'])
            ->toBeArray()
            ->toHaveCount(2);
    });

    test('accepts single HTTP method as string', function () {
        $method = new Method('GET');

        expect($method->getMethods())
            ->toBe(['GET'])
            ->toBeArray()
            ->toHaveCount(1);
    });

    test('handles permission callbacks correctly', function () {
        $methodWithDefaultPermission = new Method('GET');
        $methodWithCustomPermission = new Method('GET', 'CustomPermission');

        expect($methodWithDefaultPermission->permissionCallback)
            ->toBeNull();

        expect($methodWithCustomPermission->permissionCallback)
            ->toBe('CustomPermission');
    });
});
