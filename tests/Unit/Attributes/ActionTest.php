<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Facade;
use Pollora\Attributes\Action;
use Pollora\Hook\Infrastructure\Services\Action as ActionService;

beforeEach(function () {
    // Mock Action service
    $this->mockAction = Mockery::mock(ActionService::class);

    // Create a fake service locator
    $this->mockServiceLocator = new class($this->mockAction)
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
});

class SingleActionClass
{
    #[Action('test_action', priority: 10)]
    public function actionMethod($param = null)
    {
        // Test method
        return $param ? "processed_{$param}" : 'processed';
    }
}

class MultipleActionClass
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

class DefaultPriorityActionClass
{
    #[Action('test_action')]
    public function actionMethod($param = null)
    {
        // Test method with default priority
        return $param ? "processed_{$param}" : 'processed';
    }
}

class CustomPriorityActionClass
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

    // Test the action attribute directly using handle method
    $testClass = new SingleActionClass;
    $actionAttribute = new Action('test_action', 10);
    $methodReflection = new ReflectionMethod($testClass, 'actionMethod');
    
    $actionAttribute->handle($this->mockServiceLocator, $testClass, $methodReflection, $actionAttribute);
});

it('registers multiple action hooks with different priorities', function () {
    $testClass = new MultipleActionClass;
    
    // Test first action
    $this->mockAction->shouldReceive('add')
        ->once()
        ->withArgs(function ($hook, $callback, $priority, $acceptedArgs) {
            return $hook === 'test_action'
                && is_array($callback)
                && $callback[0] instanceof MultipleActionClass
                && $callback[1] === 'actionMethod'
                && $priority === 10;
        });

    $actionAttribute1 = new Action('test_action', 10);
    $methodReflection1 = new ReflectionMethod($testClass, 'actionMethod');
    $actionAttribute1->handle($this->mockServiceLocator, $testClass, $methodReflection1, $actionAttribute1);

    // Test second action
    $this->mockAction->shouldReceive('add')
        ->once()
        ->withArgs(function ($hook, $callback, $priority, $acceptedArgs) {
            return $hook === 'another_action'
                && is_array($callback)
                && $callback[0] instanceof MultipleActionClass
                && $callback[1] === 'anotherActionMethod'
                && $priority === 20;
        });

    $actionAttribute2 = new Action('another_action', 20);
    $methodReflection2 = new ReflectionMethod($testClass, 'anotherActionMethod');
    $actionAttribute2->handle($this->mockServiceLocator, $testClass, $methodReflection2, $actionAttribute2);
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

    // Test with default priority
    $testClass = new DefaultPriorityActionClass;
    $actionAttribute = new Action('test_action'); // No priority specified, should default to 10
    $methodReflection = new ReflectionMethod($testClass, 'actionMethod');
    
    $actionAttribute->handle($this->mockServiceLocator, $testClass, $methodReflection, $actionAttribute);
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

    // Test with custom priority
    $testClass = new CustomPriorityActionClass;
    $actionAttribute = new Action('test_action', 42);
    $methodReflection = new ReflectionMethod($testClass, 'actionMethod');
    
    $actionAttribute->handle($this->mockServiceLocator, $testClass, $methodReflection, $actionAttribute);
});

it('handles null service locator resolution gracefully', function () {
    // Create a service locator that returns null for the service
    $mockServiceLocator = new class
    {
        public function get($serviceClass)
        {
            return null;
        }
    };

    $testClass = new SingleActionClass;
    $actionAttribute = new Action('test_action', 10);
    $methodReflection = new ReflectionMethod($testClass, 'actionMethod');

    // This should not throw an exception
    $actionAttribute->handle($mockServiceLocator, $testClass, $methodReflection, $actionAttribute);

    // No assertions needed - we're just checking that it doesn't throw
    expect(true)->toBeTrue();
});

afterEach(function () {
    Mockery::close();
    Facade::clearResolvedInstances();
});
