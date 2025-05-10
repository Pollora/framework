<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Facade;
use Pollora\Attributes\Action;
use Pollora\Attributes\Attributable;
use Pollora\Attributes\AttributeProcessor;
use Pollora\Hook\Infrastructure\Services\Action as ActionService;

beforeEach(function () {
    // Mock Action service
    $this->mockAction = Mockery::mock(ActionService::class);

    // Create a fake container that will be wrapped by ContainerServiceLocator
    $this->mockContainer = new class($this->mockAction)
    {
        private $actionService;

        public function __construct($actionService)
        {
            $this->actionService = $actionService;
        }

        public function get($serviceClass)
        {
            if ($serviceClass === ActionService::class) {
                return $this->actionService;
            }

            return null;
        }
    };

    $this->processor = new AttributeProcessor($this->mockContainer);
});

class SingleActionClass implements Attributable
{
    #[Action('test_action', priority: 10)]
    public function actionMethod($param = null)
    {
        // Test method
        return $param ? "processed_{$param}" : 'processed';
    }
}

class MultipleActionClass implements Attributable
{
    #[Action('test_action', priority: 10)]
    public function actionMethod($param = null)
    {
        // Test method
        return $param ? "processed_{$param}" : 'processed';
    }

    #[Action('another_action', priority: 20)]
    public function anotherActionMethod()
    {
        // Another test method
        return 'another_processed';
    }
}

class DefaultPriorityActionClass implements Attributable
{
    #[Action('test_action')]
    public function actionMethod($param = null)
    {
        // Test method with default priority
        return $param ? "processed_{$param}" : 'processed';
    }
}

class CustomPriorityActionClass implements Attributable
{
    #[Action('test_action', priority: 42)]
    public function actionMethod($param = null)
    {
        // Test method with custom priority
        return $param ? "processed_{$param}" : 'processed';
    }
}

it('registers an action hook correctly', function () {
    // Set up expectations
    $this->mockAction->shouldReceive('add')
        ->once()
        ->withArgs(function ($hook, $callback, $priority, $acceptedArgs) {
            return $hook === 'test_action'
                && is_array($callback)
                && $callback[0] instanceof SingleActionClass
                && $callback[1] === 'actionMethod'
                && $priority === 10
                && $acceptedArgs === 1;
        });

    // Process the test class
    $testClass = new SingleActionClass;
    $this->processor->process($testClass);
});

it('registers multiple action hooks with different priorities', function () {
    // Set up expectations for both actions
    $this->mockAction->shouldReceive('add')
        ->once()
        ->withArgs(function ($hook, $callback, $priority, $acceptedArgs) {
            return $hook === 'test_action'
                && is_array($callback)
                && $callback[0] instanceof MultipleActionClass
                && $callback[1] === 'actionMethod'
                && $priority === 10;
        });

    $this->mockAction->shouldReceive('add')
        ->once()
        ->withArgs(function ($hook, $callback, $priority, $acceptedArgs) {
            return $hook === 'another_action'
                && is_array($callback)
                && $callback[0] instanceof MultipleActionClass
                && $callback[1] === 'anotherActionMethod'
                && $priority === 20;
        });

    // Process the test class
    $testClass = new MultipleActionClass;
    $this->processor->process($testClass);
});

it('registers an action hook with default priority (10)', function () {
    // Set up expectations
    $this->mockAction->shouldReceive('add')
        ->once()
        ->withArgs(function ($hook, $callback, $priority, $acceptedArgs) {
            return $hook === 'test_action'
                && is_array($callback)
                && $callback[0] instanceof DefaultPriorityActionClass
                && $callback[1] === 'actionMethod'
                && $priority === 10 // Default priority should be 10
                && $acceptedArgs === 1;
        });

    // Process the test class
    $testClass = new DefaultPriorityActionClass;
    $this->processor->process($testClass);
});

it('registers an action hook with custom priority', function () {
    // Set up expectations
    $this->mockAction->shouldReceive('add')
        ->once()
        ->withArgs(function ($hook, $callback, $priority, $acceptedArgs) {
            return $hook === 'test_action'
                && is_array($callback)
                && $callback[0] instanceof CustomPriorityActionClass
                && $callback[1] === 'actionMethod'
                && $priority === 42 // Custom priority
                && $acceptedArgs === 1;
        });

    // Process the test class
    $testClass = new CustomPriorityActionClass;
    $this->processor->process($testClass);
});

it('handles null service locator resolution gracefully', function () {
    // Create a container that returns null for the service
    $mockContainer = new class
    {
        public function get($serviceClass)
        {
            return null;
        }
    };

    $processor = new AttributeProcessor($mockContainer);
    $testClass = new SingleActionClass;

    // This should not throw an exception
    $processor->process($testClass);

    // No assertions needed - we're just checking that it doesn't throw
    expect(true)->toBeTrue();
});

afterEach(function () {
    Mockery::close();
    Facade::clearResolvedInstances();
});
