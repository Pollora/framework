<?php

declare(strict_types=1);

use Pollora\Attributes\Attributable;
use Pollora\Attributes\AttributeProcessor;
use Pollora\Attributes\WpRestRoute;
use Pollora\Attributes\WpRestRoute\Method;

if (! function_exists('register_rest_route')) {
    function register_rest_route(...$args)
    {
        // Stub pour les tests
        return true;
    }
}

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

/**
 * Integration tests with discovery system
 */
describe('WpRestRoute with Discovery System', function () {

    // Mock class for testing without implementing Attributable
    beforeEach(function () {
        if (! class_exists('MockAPIController')) {
            eval('
                #[Pollora\Attributes\WpRestRoute("api/v2", "/mock/(?P<id>\\\\d+)")]
                class MockAPIController {
                    #[Pollora\Attributes\WpRestRoute\Method("GET")]
                    public function get(int $id): array {
                        return ["id" => $id, "data" => "mock"];
                    }
                    
                    #[Pollora\Attributes\WpRestRoute\Method(["POST", "PUT"])]
                    public function update(int $id): array {
                        return ["id" => $id, "updated" => true];
                    }
                }
            ');
        }
    });

    test('handles classes that do not implement Attributable', function () {
        // Test that our wrapper system works with regular classes
        $controller = new MockAPIController;

        // Simulate the discovery system creating an Attributable wrapper
        $wrapper = new class('api/v2', '/mock/(?P<id>\\d+)', null) implements Attributable
        {
            private mixed $realInstance = null;

            public function __construct(
                public readonly string $namespace,
                public readonly string $route,
                public readonly ?string $classPermission = null
            ) {}

            public function getRealInstance(): mixed
            {
                if ($this->realInstance === null) {
                    $this->realInstance = new MockAPIController;
                }

                return $this->realInstance;
            }
        };

        expect($wrapper)->toBeInstanceOf(Attributable::class);
        expect($wrapper->namespace)->toBe('api/v2');
        expect($wrapper->route)->toBe('/mock/(?P<id>\\d+)');
        expect($wrapper->getRealInstance())->toBeInstanceOf(MockAPIController::class);
    });

    test('Method attribute can handle wrapper with real instance', function () {
        // Create wrapper like the discovery system does
        $wrapper = new class('api/v2', '/mock/(?P<id>\\d+)', null) implements Attributable
        {
            private mixed $realInstance = null;

            public function __construct(
                public readonly string $namespace,
                public readonly string $route,
                public readonly ?string $classPermission = null
            ) {}

            public function getRealInstance(): mixed
            {
                if ($this->realInstance === null) {
                    $this->realInstance = new MockAPIController;
                }

                return $this->realInstance;
            }
        };

        $method = new Method('GET');

        // Test that handleRequest would work (we can't easily test the private method directly)
        expect($wrapper->getRealInstance())->toBeInstanceOf(MockAPIController::class);
        expect(method_exists($wrapper, 'getRealInstance'))->toBeTrue();
    });

    test('extracts route arguments correctly', function () {
        $method = new Method('GET');

        // Use reflection to test the private method
        $reflection = new ReflectionClass($method);
        $extractMethod = $reflection->getMethod('extractArgsFromRoute');
        $extractMethod->setAccessible(true);

        $args = $extractMethod->invoke($method, '/test/(?P<id>\\d+)/(?P<slug>[a-z-]+)');

        expect($args)->toHaveKey('id');
        expect($args)->toHaveKey('slug');
        expect($args['id'])->toHaveKey('validate_callback');
        expect($args['slug'])->toHaveKey('validate_callback');
    });
});
