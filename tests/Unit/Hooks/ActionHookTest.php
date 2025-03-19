<?php

namespace Tests\Unit\Hooks;

use Pollora\Hook\Action;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use WP;

// Integration of Mockery with PHPUnit
uses(MockeryPHPUnitIntegration::class);

beforeEach(function () {
    // Reset WordPress mocks before each test
    setupWordPressMocks();

    // Fresh instance of Action for each test
    $this->action = new Action();
});

// Test group for the add method
describe('Action::add', function () {
    it('adds a hook with default values', function () {
        $callback = function () {
            return 'test';
        };

        // Expectation setup for add_action
        WP::$wpFunctions->shouldReceive('add_filter')
            ->with('test_hook', \Mockery::type('callable'), 10, 1)
            ->once()
            ->andReturn(true);

        $this->action->add('test_hook', $callback);

        // Verify that the hook has been registered in the index
        expect($this->action->exists('test_hook'))->toBeTrue();
    });

    it('adds a hook with a custom priority', function () {
        $callback = function () {
            return 'test';
        };

        // Expectation setup for add_action
        WP::$wpFunctions->shouldReceive('add_filter')
            ->with('test_hook', \Mockery::type('callable'), 20, 1)
            ->once()
            ->andReturn(true);

        $this->action->add('test_hook', $callback, 20);

        // Verify that the hook has been registered correctly
        expect($this->action->exists('test_hook', $callback, 20))->toBeTrue();
        expect($this->action->exists('test_hook', $callback, 10))->toBeFalse();
    });

    it('automatically detects the number of arguments', function () {
        $callback = function ($arg1, $arg2, $arg3) {
            return $arg1 . $arg2 . $arg3;
        };

        // Expectation setup for add_action
        // The method should detect 3 arguments
        WP::$wpFunctions->shouldReceive('add_filter')
            ->with('test_hook', \Mockery::type('callable'), 10, 3)
            ->once()
            ->andReturn(true);

        $this->action->add('test_hook', $callback);

        // Simple verification that the action has been added
        expect($this->action->exists('test_hook'))->toBeTrue();
    });

    it('respects the explicitly specified number of arguments', function () {
        $callback = function ($arg1, $arg2, $arg3) {
            return $arg1 . $arg2 . $arg3;
        };

        // Expectation setup for add_action
        // Even though the function accepts 3 arguments, we specify 2
        WP::$wpFunctions->shouldReceive('add_filter')
            ->with('test_hook', \Mockery::type('callable'), 10, 2)
            ->once()
            ->andReturn(true);

        $this->action->add('test_hook', $callback, 10, 2);

        // Verify that the action has been added
        expect($this->action->exists('test_hook'))->toBeTrue();
    });
});

// Test group for the remove method
describe('Action::remove', function () {
    it('removes an existing hook', function () {
        $callback = function () {
            return 'test';
        };

        // First, add the hook
        WP::$wpFunctions->shouldReceive('add_filter')
            ->with('test_hook', \Mockery::type('callable'), 10, 1)
            ->once()
            ->andReturn(true);

        $this->action->add('test_hook', $callback);

        // Set expectation for remove_action
        WP::$wpFunctions->shouldReceive('remove_filter')
            ->with('test_hook', \Mockery::type('callable'), 10)
            ->once()
            ->andReturn(true);

        $this->action->remove('test_hook', $callback);

        // Verify that the hook has been removed from the index
        expect($this->action->exists('test_hook', $callback))->toBeFalse();
    });

    it('handles removing non-existent hooks correctly', function () {
        $callback = function () {
            return 'test';
        };

        // Set expectation for remove_action
        WP::$wpFunctions->shouldReceive('remove_filter')
            ->with('nonexistent_hook', \Mockery::type('callable'), 10)
            ->once()
            ->andReturn(false);

        $this->action->remove('nonexistent_hook', $callback);

        // Should not fail even if the hook does not exist
        expect(true)->toBeTrue();
    });
});

// Test group for the exists method
describe('Action::exists', function () {
    it('correctly detects existing hooks', function () {
        $callback = function () {
            return 'test';
        };

        // Add the hook
        WP::$wpFunctions->shouldReceive('add_filter')
            ->andReturn(true);
        $this->action->add('test_hook', $callback);

        // Verifications
        expect($this->action->exists('test_hook'))->toBeTrue();
        expect($this->action->exists('test_hook', $callback))->toBeTrue();
        expect($this->action->exists('test_hook', $callback, 10))->toBeTrue();
    });

    it('correctly detects non-existent hooks', function () {
        $callback = function () {
            return 'test';
        };

        // Basic tests
        expect($this->action->exists('nonexistent_hook'))->toBeFalse();

        // Add a hook and test variations
        WP::$wpFunctions->shouldReceive('add_filter')
            ->andReturn(true);
        $this->action->add('test_hook', $callback);

        // Different combinations that should return false
        $differentCallback = function () {};
        expect($this->action->exists('test_hook', $differentCallback))->toBeFalse();
        expect($this->action->exists('test_hook', $callback, 20))->toBeFalse();
    });
});

// Test group for the do methods
describe('Action::do methods', function () {
    it('executes do_action with the correct arguments', function () {
        // Prepare test data
        $hook = 'test_action';
        $arg1 = 'value1';
        $arg2 = 'value2';

        // Set expectation
        WP::$wpFunctions->shouldReceive('do_action')
            ->once()
            ->with($hook, $arg1, $arg2)
            ->andReturn(null);

        // Execute the method
        $this->action->do($hook, $arg1, $arg2);

        // Verify that the expectation is met
        expect(true)->toBeTrue();
    });

    it('executes do_action_array with the correct arguments', function () {
        // Prepare test data
        $hook = 'test_action';
        $args = ['value1', 'value2'];

        // Set expectation
        WP::$wpFunctions->shouldReceive('do_action_array')
            ->once()
            ->with($hook, $args)
            ->andReturn(null);

        // Execute the method
        $this->action->doArray($hook, $args);

        // Verify that the expectation is met
        expect(true)->toBeTrue();
    });

    it('executes do_action_once with the correct arguments', function () {
        // Prepare test data
        $hook = 'test_action';
        $arg1 = 'value1';
        $arg2 = 'value2';

        // Set expectation
        WP::$wpFunctions->shouldReceive('do_action_once')
            ->once()
            ->with($hook, $arg1, $arg2)
            ->andReturn(null);

        // Execute the method
        $this->action->doOnce($hook, $arg1, $arg2);

        // Verify that the expectation is met
        expect(true)->toBeTrue();
    });
});

// Test group for the getCallbacks method
describe('Action::getCallbacks', function () {
    it('retrieves callbacks associated with a hook', function () {
        // Prepare expected callbacks
        $mockCallbacks = [
            'callback1' => ['function' => 'someFunction'],
            'callback2' => ['function' => 'anotherFunction']
        ];

        // Mock global $wp_filter
        global $wp_filter;
        $wp_filter['test_hook'] = (object) [
            'callbacks' => [
                10 => $mockCallbacks
            ]
        ];

        // Retrieve callbacks
        $callbacks = $this->action->getCallbacks('test_hook');

        // Verify results
        expect($callbacks)->toBe($wp_filter['test_hook']->callbacks);
    });

    it('retrieves callbacks for a specific priority', function () {
        // Prepare expected callbacks
        $mockCallbacks10 = [
            'callback1' => ['function' => 'someFunction']
        ];

        $mockCallbacks20 = [
            'callback2' => ['function' => 'anotherFunction']
        ];

        // Mock global $wp_filter
        global $wp_filter;
        $wp_filter['test_hook'] = (object) [
            'callbacks' => [
                10 => $mockCallbacks10,
                20 => $mockCallbacks20
            ]
        ];

        // Retrieve callbacks for priority 20
        $callbacks = $this->action->getCallbacks('test_hook', 20);

        // Verify results
        expect($callbacks)->toBe($mockCallbacks20);
    });

    it('returns an empty array for a non-existent hook', function () {
        $callbacks = $this->action->getCallbacks('nonexistent_hook');
        expect($callbacks)->toBeArray()->toBeEmpty();
    });
});
