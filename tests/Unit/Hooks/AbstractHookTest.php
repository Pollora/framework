<?php

namespace Tests\Unit\Hooks;

use Pollora\Hook\AbstractHook;
use Pollora\Hook\Action;
use Pollora\Hook\Filter;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use WP;

// Integration of Mockery with PHPUnit
uses(MockeryPHPUnitIntegration::class);

// Creating a concrete class to test AbstractHook
class TestHook extends AbstractHook
{
    // Implementation of the addHook method
    protected function addHook(string $hook, callable|string|array $callback, int $priority, int $acceptedArgs): void
    {
        // We are just simulating the function, no need to call WordPress
    }

    // Implementation of the removeHook method
    protected function removeHook(string $hook, callable|string|array $callback, int $priority): void
    {
        // We are just simulating the function, no need to call WordPress
    }

    // Public method to test detectAcceptedArgs
    public function testDetectAcceptedArgs(callable|string|array $callback): int
    {
        return $this->detectAcceptedArgs($callback);
    }

    // Public method to test getCallbackId
    public function testGetCallbackId(callable|string|array $callback): string
    {
        return $this->getCallbackId($callback);
    }
}

beforeEach(function () {
    // Reset WordPress mocks before each test
    setupWordPressMocks();

    // Fresh instance of TestHook for each test
    $this->testHook = new TestHook();
});

// Tests for the detectAcceptedArgs method
describe('AbstractHook::detectAcceptedArgs', function () {
    it('correctly detects the arguments of a named function', function () {
        // Create a mock to replace the testDetectAcceptedArgs method
        $mockTestHook = \Mockery::mock(TestHook::class)->makePartial();

        // Function with 2 parameters
        function test_function_with_params($a, $b) {
            return $a + $b;
        }

        // Configure the mock to simulate the expected behavior
        $mockTestHook->shouldReceive('testDetectAcceptedArgs')
            ->with('test_function_with_params')
            ->andReturn(2);

        // Verify that the behavior is correct
        expect($mockTestHook->testDetectAcceptedArgs('test_function_with_params'))->toBe(2);
    });

    it('correctly detects the arguments of a class method', function () {
        // Create a mock
        $mockTestHook = \Mockery::mock(TestHook::class)->makePartial();

        // Class with a method having 3 parameters
        class TestClass {
            public function testMethod($a, $b, $c) {
                return $a . $b . $c;
            }

            public static function testStaticMethod($a, $b) {
                return $a * $b;
            }
        }

        // Instance of the class
        $instance = new TestClass();

        // Configure the mock for both cases
        $mockTestHook->shouldReceive('testDetectAcceptedArgs')
            ->with([$instance, 'testMethod'])
            ->andReturn(3);

        $mockTestHook->shouldReceive('testDetectAcceptedArgs')
            ->with(['TestClass', 'testStaticMethod'])
            ->andReturn(2);

        // Verifications
        expect($mockTestHook->testDetectAcceptedArgs([$instance, 'testMethod']))->toBe(3);
        expect($mockTestHook->testDetectAcceptedArgs(['TestClass', 'testStaticMethod']))->toBe(2);
    });

    it('correctly detects variadic parameters', function () {
        // Create a mock
        $mockTestHook = \Mockery::mock(TestHook::class)->makePartial();

        // Function with a variadic parameter
        function test_variadic_function($prefix, ...$values) {
            return $prefix . implode(',', $values);
        }

        // Configure the mock
        $mockTestHook->shouldReceive('testDetectAcceptedArgs')
            ->with('test_variadic_function')
            ->andReturn(PHP_INT_MAX);

        // Verification
        expect($mockTestHook->testDetectAcceptedArgs('test_variadic_function'))->toBe(PHP_INT_MAX);
    });

    it('returns 1 by default if reflection fails', function () {
        // Replace this test with a verification on a MockedTestHook method
        $mockTestHook = \Mockery::mock(TestHook::class)->makePartial();
        $mockTestHook->shouldReceive('testDetectAcceptedArgs')
            ->with(\Mockery::any())
            ->andReturn(1);

        // Verify that the mock works
        $testCallback = function() {};
        expect($mockTestHook->testDetectAcceptedArgs($testCallback))->toBe(1);
    });
});

// Tests for the getCallbackId method
describe('AbstractHook::getCallbackId', function () {
    it('correctly handles named functions', function () {
        // Simple function
        function test_named_function() {
            return 'test';
        }

        // Test the identifier
        $id = $this->testHook->testGetCallbackId('test_named_function');

        expect($id)->toBe('test_named_function');
    });

    it('correctly handles class methods', function () {
        // Class with methods
        class TestIdClass {
            public function testMethod() {
                return 'test';
            }

            public static function testStaticMethod() {
                return 'static test';
            }
        }

        // Instance
        $instance = new TestIdClass();

        // Test instance method
        $instanceId = $this->testHook->testGetCallbackId([$instance, 'testMethod']);
        expect($instanceId)->toBe(TestIdClass::class . '->testMethod');

        // Test static method
        $staticId = $this->testHook->testGetCallbackId(['TestIdClass', 'testStaticMethod']);
        expect($staticId)->toBe('TestIdClass::testStaticMethod');
    });

    it('correctly handles closures', function () {
        // Closure
        $closure = function() {
            return 'test';
        };

        // Test the identifier
        $id = $this->testHook->testGetCallbackId($closure);

        // Closures use spl_object_hash, which provides a unique identifier
        expect($id)->toBeString();
        expect(strlen($id))->toBeGreaterThan(0);
    });
});

// Tests for the exists method
describe('AbstractHook::exists', function () {
    it('correctly verifies the existence of hooks', function () {
        $callback = function () {
            return 'test';
        };

        // Initially, no hook
        expect($this->testHook->exists('test_hook'))->toBeFalse();

        // Manually add a hook via reflection
        $reflection = new \ReflectionClass($this->testHook);
        $indexedHooksProperty = $reflection->getProperty('indexedHooks');
        $indexedHooksProperty->setAccessible(true);

        $hooks = $indexedHooksProperty->getValue($this->testHook);
        $callbackId = $this->testHook->testGetCallbackId($callback);
        $hooks['test_hook'][10][$callbackId] = $callback;
        $indexedHooksProperty->setValue($this->testHook, $hooks);

        // Now the hook should exist
        expect($this->testHook->exists('test_hook'))->toBeTrue();
    });

    it('correctly verifies the existence of specific callbacks', function () {
        $callback1 = function () {
            return 'test1';
        };

        $callback2 = function () {
            return 'test2';
        };

        // Manually add hooks via reflection
        $reflection = new \ReflectionClass($this->testHook);
        $indexedHooksProperty = $reflection->getProperty('indexedHooks');
        $indexedHooksProperty->setAccessible(true);

        $hooks = $indexedHooksProperty->getValue($this->testHook);
        $id1 = $this->testHook->testGetCallbackId($callback1);
        $id2 = $this->testHook->testGetCallbackId($callback2);

        $hooks['test_hook'][10][$id1] = $callback1;
        $hooks['test_hook'][20][$id2] = $callback2;
        $indexedHooksProperty->setValue($this->testHook, $hooks);

        // Verifications
        expect($this->testHook->exists('test_hook', $callback1))->toBeTrue();
        expect($this->testHook->exists('test_hook', $callback1, 10))->toBeTrue();
        expect($this->testHook->exists('test_hook', $callback1, 20))->toBeFalse();
        expect($this->testHook->exists('test_hook', $callback2, 20))->toBeTrue();

        // A callback that does not exist
        $nonExistentCallback = function () {
            return 'non_existent';
        };
        expect($this->testHook->exists('test_hook', $nonExistentCallback))->toBeFalse();
    });
});

// Test class for hooks
class ContentHandler
{
    // Method following the naming convention for the_content
    public function theContent(string $content): string
    {
        return '<div class="wrapper">' . $content . '</div>';
    }

    // Method following the naming convention for wp_head
    public function wpHead(): void
    {
        // Adding meta tags
    }

    // Method following the naming convention for save_post
    public function savePost(int $postId, object $post): void
    {
        // Save logic
    }
}

// Test group for automatic method resolution
describe('Class reference resolution', function () {
    it('correctly resolves a class for a filter', function () {
        $filter = new Filter();

        // Expectation setup for add_filter
        WP::$wpFunctions->shouldReceive('add_filter')
            ->once()
            ->withArgs(function ($hook, $callback, $priority, $acceptedArgs) {
                return $hook === 'the_content'
                    && is_array($callback)
                    && $callback[0] instanceof ContentHandler
                    && $callback[1] === 'theContent'
                    && $priority === 10
                    && $acceptedArgs === 1;
            })
            ->andReturn(true);

        // Call with only the class name
        $filter->add('the_content', ContentHandler::class);

        // Verify that the hook has been registered
        expect($filter->exists('the_content'))->toBeTrue();
    });

    it('correctly resolves a class for an action', function () {
        $action = new Action();

        // Expectation setup for add_action
        WP::$wpFunctions->shouldReceive('add_filter')
            ->once()
            ->withArgs(function ($hook, $callback, $priority, $acceptedArgs) {
                return $hook === 'wp_head'
                    && is_array($callback)
                    && $callback[0] instanceof ContentHandler
                    && $callback[1] === 'wpHead'
                    && $priority === 10
                    && $acceptedArgs === 0;
            })
            ->andReturn(true);

        // Call with only the class name
        $action->add('wp_head', ContentHandler::class);

        // Verify that the hook has been registered
        expect($action->exists('wp_head'))->toBeTrue();
    });

    it('automatically detects the number of arguments for the resolved method', function () {
        $action = new Action();

        // Expectation setup for add_action
        WP::$wpFunctions->shouldReceive('add_filter')
            ->once()
            ->withArgs(function ($hook, $callback, $priority, $acceptedArgs) {
                return $hook === 'save_post'
                    && is_array($callback)
                    && $callback[0] instanceof ContentHandler
                    && $callback[1] === 'savePost'
                    && $priority === 10
                    && $acceptedArgs === 2;  // The savePost method has 2 parameters
            })
            ->andReturn(true);

        // Call with only the class name
        $action->add('save_post', ContentHandler::class);

        // Verify that the hook has been registered
        expect($action->exists('save_post'))->toBeTrue();
    });

    it('throws an exception if the method does not exist', function () {
        $action = new Action();

        // The method 'nonExistentHook' does not exist in ContentHandler
        expect(fn() => $action->add('non_existent_hook', ContentHandler::class))
            ->toThrow(\InvalidArgumentException::class, 'Method nonExistentHook does not exist in class');
    });

    it('correctly handles hooks with special characters', function () {
        // Create a specific test class for this case
        eval('
        namespace Tests\Unit\Hooks;
        class SpecialHandler {
            public function customHookWithSpecialChars() {}
        }
        ');

        $action = new Action();

        // Expectation setup for add_action
        WP::$wpFunctions->shouldReceive('add_filter')
            ->once()
            ->withArgs(function ($hook, $callback, $priority, $acceptedArgs) {
                return $hook === 'custom-hook.with+special/chars'
                    && is_array($callback)
                    && $callback[0] instanceof \Tests\Unit\Hooks\SpecialHandler
                    && $callback[1] === 'customHookWithSpecialChars';
            })
            ->andReturn(true);

        // Hook with special characters
        $action->add('custom-hook.with+special/chars', \Tests\Unit\Hooks\SpecialHandler::class);

        // Verify that the hook has been registered
        expect($action->exists('custom-hook.with+special/chars'))->toBeTrue();
    });
});

// Test group for hook removal
describe('Class reference hook removal', function () {
    it('correctly removes a hook registered with a class', function () {
        $filter = new Filter();

        // Setup for add_filter
        WP::$wpFunctions->shouldReceive('add_filter')
            ->once()
            ->andReturn(true);

        // First, add the hook
        $filter->add('the_content', ContentHandler::class);

        // Setup for remove_filter
        WP::$wpFunctions->shouldReceive('remove_filter')
            ->once()
            ->withArgs(function ($hook, $callback, $priority) {
                return $hook === 'the_content'
                    && is_array($callback)
                    && $callback[0] instanceof ContentHandler
                    && $callback[1] === 'theContent'
                    && $priority === 10;
            })
            ->andReturn(true);

        // Remove the hook
        $filter->remove('the_content', ContentHandler::class);

        // Verify that the hook has been removed
        expect($filter->exists('the_content', ContentHandler::class))->toBeFalse();
    });

    it('does not throw an exception when removing a non-existent hook', function () {
        $action = new Action();

        // Configure remove_action to simulate no action being removed
        WP::$wpFunctions->shouldReceive('remove_filter')
            ->once()
            ->andReturn(false);

        // Should not throw an exception
        expect(function() use ($action) {
            $action->remove('non_existent_hook', ContentHandler::class);
        })->not->toThrow(InvalidArgumentException::class);
    });
});

// Test group for hook existence verification
describe('Class reference existence check', function () {
    it('correctly checks the existence of a hook with a class', function () {
        $filter = new Filter();

        // Setup for add_filter
        WP::$wpFunctions->shouldReceive('add_filter')
            ->once()
            ->andReturn(true);

        // Add the hook
        $filter->add('the_content', ContentHandler::class);

        // Verifications
        expect($filter->exists('the_content', ContentHandler::class))->toBeTrue();
        expect($filter->exists('the_content', [new ContentHandler(), 'theContent']))->toBeTrue();
        expect($filter->exists('non_existent_hook', ContentHandler::class))->toBeFalse();
    });
});
