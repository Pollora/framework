<?php

declare(strict_types=1);

require_once __DIR__.'/../helpers.php';

use Mockery\MockInterface;
use Pollora\Attributes\Action;
use Pollora\Attributes\Attributable;
use Pollora\Attributes\AttributeProcessor;
use Pollora\Hook\Infrastructure\Services\Action as ActionService;

beforeEach(function (): void {
    // Mock the Action service for testing hook registration
    $this->mockAction = Mockery::mock(ActionService::class);

    // Use generic TestContainer helper for service injection
    $this->mockContainer = new TestContainer([
        ActionService::class => $this->mockAction,
    ]);

    // Initialize the processor with our test container
    $this->processor = new AttributeProcessor($this->mockContainer);
});

/**
 * Test class with a single action hook
 */
class SingleActionClass implements Attributable
{
    /**
     * Method with a single action attribute
     */
    #[Action('test_action', priority: 10)]
    public function actionMethod(?string $param = null): string
    {
        return $param ? "processed_{$param}" : 'processed';
    }
}

/**
 * Test class with multiple action hooks
 */
class MultipleActionClass implements Attributable
{
    /**
     * First action method
     */
    #[Action('test_action', priority: 10)]
    public function actionMethod(?string $param = null): string
    {
        return $param ? "processed_{$param}" : 'processed';
    }

    /**
     * Second action method with different hook and priority
     */
    #[Action('another_action', priority: 20)]
    public function anotherActionMethod(): string
    {
        return 'another_processed';
    }
}

/**
 * Test class with default priority action hook
 */
class DefaultPriorityActionClass implements Attributable
{
    /**
     * Method with default priority (should be 10)
     */
    #[Action('test_action')]
    public function actionMethod(?string $param = null): string
    {
        return $param ? "processed_{$param}" : 'processed';
    }
}

/**
 * Test class with custom priority action hook
 */
class CustomPriorityActionClass implements Attributable
{
    /**
     * Method with custom priority value
     */
    #[Action('test_action', priority: 42)]
    public function actionMethod(?string $param = null): string
    {
        return $param ? "processed_{$param}" : 'processed';
    }
}

it('registers an action hook correctly', function (): void {
    // Arrange: Set up expectations for action service
    /** @var MockInterface $this->mockAction */
    $this->mockAction->shouldReceive('add')
        ->once()
        ->withArgs(function (string $hook, ?array $callback, int $priority, int $acceptedArgs): bool {
            // Verify all parameters are correctly passed to the action service
            return $hook === 'test_action'
                && is_array($callback)
                && $callback[0] instanceof SingleActionClass
                && $callback[1] === 'actionMethod'
                && $priority === 10
                && $acceptedArgs === 1;
        });

    // Act: Process the test class with attributes
    $testClass = new SingleActionClass;
    $this->processor->process($testClass);

    // Assert: Verification happens in the mock expectations
});

it('registers multiple action hooks with different priorities', function (): void {
    // Arrange: Set up expectations for both action hooks
    /** @var MockInterface $this->mockAction */
    $this->mockAction->shouldReceive('add')
        ->once()
        ->withArgs(function (string $hook, ?array $callback, int $priority, int $acceptedArgs): bool {
            return $hook === 'test_action'
                && is_array($callback)
                && $callback[0] instanceof MultipleActionClass
                && $callback[1] === 'actionMethod'
                && $priority === 10;
        });

    $this->mockAction->shouldReceive('add')
        ->once()
        ->withArgs(function (string $hook, ?array $callback, int $priority, int $acceptedArgs): bool {
            return $hook === 'another_action'
                && is_array($callback)
                && $callback[0] instanceof MultipleActionClass
                && $callback[1] === 'anotherActionMethod'
                && $priority === 20;
        });

    // Act: Process the test class with multiple attributes
    $testClass = new MultipleActionClass;
    $this->processor->process($testClass);

    // Assert: Verification happens in the mock expectations
});

it('registers an action hook with default priority (10)', function (): void {
    // Arrange: Set up expectations for default priority
    /** @var MockInterface $this->mockAction */
    $this->mockAction->shouldReceive('add')
        ->once()
        ->withArgs(function (string $hook, ?array $callback, int $priority, int $acceptedArgs): bool {
            return $hook === 'test_action'
                && is_array($callback)
                && $callback[0] instanceof DefaultPriorityActionClass
                && $callback[1] === 'actionMethod'
                && $priority === 10 // Verify default priority is 10
                && $acceptedArgs === 1;
        });

    // Act: Process the test class with default priority attribute
    $testClass = new DefaultPriorityActionClass;
    $this->processor->process($testClass);

    // Assert: Verification happens in the mock expectations
});

it('registers an action hook with custom priority', function (): void {
    // Arrange: Set up expectations for custom priority
    /** @var MockInterface $this->mockAction */
    $this->mockAction->shouldReceive('add')
        ->once()
        ->withArgs(function (string $hook, ?array $callback, int $priority, int $acceptedArgs): bool {
            return $hook === 'test_action'
                && is_array($callback)
                && $callback[0] instanceof CustomPriorityActionClass
                && $callback[1] === 'actionMethod'
                && $priority === 42 // Verify custom priority is respected
                && $acceptedArgs === 1;
        });

    // Act: Process the test class with custom priority attribute
    $testClass = new CustomPriorityActionClass;
    $this->processor->process($testClass);

    // Assert: Verification happens in the mock expectations
});

it('handles null service locator resolution gracefully', function (): void {
    // Arrange: Create a container that returns null for the service
    $mockContainer = new class
    {
        public function get(string $serviceClass): ?object
        {
            return null;
        }
    };

    // Act: Initialize processor with null-returning container and process a class
    $processor = new AttributeProcessor($mockContainer);
    $testClass = new SingleActionClass;

    // This should not throw an exception
    $result = $processor->process($testClass);

    // Assert: Method completes without exceptions
    expect(true)->toBeTrue();
});

afterEach(function (): void {
    Mockery::close();
});
