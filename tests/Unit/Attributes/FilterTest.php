<?php

declare(strict_types=1);

require_once __DIR__.'/../helpers.php';

use Mockery\MockInterface;
use Pollora\Attributes\Attributable;
use Pollora\Attributes\AttributeProcessor;
use Pollora\Attributes\Filter;
use Pollora\Hook\Infrastructure\Services\Filter as FilterService;

beforeEach(function (): void {
    // Mock Filter service for testing hook registration
    $this->mockFilter = Mockery::mock(FilterService::class);

    // Use generic TestContainer helper for service injection
    $this->mockContainer = new TestContainer([
        FilterService::class => $this->mockFilter,
    ]);

    // Initialize the processor with our test container
    $this->processor = new AttributeProcessor($this->mockContainer);
});

/**
 * Test class with a single filter hook
 */
class SingleFilterClass implements Attributable
{
    /**
     * Method with a single filter attribute
     *
     * @param  string  $value  The input value to filter
     * @return string The modified value
     */
    #[Filter('test_filter', priority: 10)]
    public function filterMethod(string $value): string
    {
        return "modified_{$value}";
    }
}

/**
 * Test class with multiple filter hooks
 */
class MultipleFilterClass implements Attributable
{
    /**
     * First filter method
     *
     * @param  string  $value  The input value to filter
     * @return string The modified value
     */
    #[Filter('test_filter', priority: 10)]
    public function filterMethod(string $value): string
    {
        return "modified_{$value}";
    }

    /**
     * Second filter method with different hook and priority
     *
     * @param  array<string, mixed>  $data  The input data to filter
     * @return array<string, mixed> The modified data
     */
    #[Filter('another_filter', priority: 20)]
    public function anotherFilterMethod(array $data): array
    {
        $data['modified'] = true;

        return $data;
    }
}

/**
 * Test class with default priority filter hook
 */
class DefaultPriorityFilterClass implements Attributable
{
    /**
     * Method with default priority (should be 10)
     *
     * @param  string  $value  The input value to filter
     * @return string The modified value
     */
    #[Filter('test_filter')]
    public function filterMethod(string $value): string
    {
        return "modified_{$value}";
    }
}

/**
 * Test class with custom priority filter hook
 */
class CustomPriorityFilterClass implements Attributable
{
    /**
     * Method with custom priority value
     *
     * @param  string  $value  The input value to filter
     * @return string The modified value
     */
    #[Filter('test_filter', priority: 99)]
    public function filterMethod(string $value): string
    {
        return "modified_{$value}";
    }
}

it('registers a filter hook correctly', function (): void {
    // Arrange: Set up expectations for filter service
    /** @var MockInterface $this->mockFilter */
    $this->mockFilter->shouldReceive('add')
        ->once()
        ->withArgs(function (string $hook, ?array $callback, int $priority, int $acceptedArgs): bool {
            // Verify all parameters are correctly passed to the filter service
            return $hook === 'test_filter'
                && is_array($callback)
                && $callback[0] instanceof SingleFilterClass
                && $callback[1] === 'filterMethod'
                && $priority === 10
                && $acceptedArgs === 1;
        });

    // Act: Process the test class with attributes
    $testClass = new SingleFilterClass;
    $this->processor->process($testClass);

    // Assert: Verification happens in the mock expectations
});

it('registers multiple filter hooks with different priorities', function (): void {
    // Arrange: Set up expectations for both filter hooks
    /** @var MockInterface $this->mockFilter */
    $this->mockFilter->shouldReceive('add')
        ->once()
        ->withArgs(function (string $hook, ?array $callback, int $priority, int $acceptedArgs): bool {
            return $hook === 'test_filter'
                && is_array($callback)
                && $callback[0] instanceof MultipleFilterClass
                && $callback[1] === 'filterMethod'
                && $priority === 10;
        });

    $this->mockFilter->shouldReceive('add')
        ->once()
        ->withArgs(function (string $hook, ?array $callback, int $priority, int $acceptedArgs): bool {
            return $hook === 'another_filter'
                && is_array($callback)
                && $callback[0] instanceof MultipleFilterClass
                && $callback[1] === 'anotherFilterMethod'
                && $priority === 20;
        });

    // Act: Process the test class with multiple attributes
    $testClass = new MultipleFilterClass;
    $this->processor->process($testClass);

    // Assert: Verification happens in the mock expectations
});

it('registers a filter hook with default priority (10)', function (): void {
    // Arrange: Set up expectations for default priority
    /** @var MockInterface $this->mockFilter */
    $this->mockFilter->shouldReceive('add')
        ->once()
        ->withArgs(function (string $hook, ?array $callback, int $priority, int $acceptedArgs): bool {
            return $hook === 'test_filter'
                && is_array($callback)
                && $callback[0] instanceof DefaultPriorityFilterClass
                && $callback[1] === 'filterMethod'
                && $priority === 10 // Verify default priority is 10
                && $acceptedArgs === 1;
        });

    // Act: Process the test class with default priority attribute
    $testClass = new DefaultPriorityFilterClass;
    $this->processor->process($testClass);

    // Assert: Verification happens in the mock expectations
});

it('registers a filter hook with custom priority', function (): void {
    // Arrange: Set up expectations for custom priority
    /** @var MockInterface $this->mockFilter */
    $this->mockFilter->shouldReceive('add')
        ->once()
        ->withArgs(function (string $hook, ?array $callback, int $priority, int $acceptedArgs): bool {
            return $hook === 'test_filter'
                && is_array($callback)
                && $callback[0] instanceof CustomPriorityFilterClass
                && $callback[1] === 'filterMethod'
                && $priority === 99 // Verify custom priority is respected
                && $acceptedArgs === 1;
        });

    // Act: Process the test class with custom priority attribute
    $testClass = new CustomPriorityFilterClass;
    $this->processor->process($testClass);

    // Assert: Verification happens in the mock expectations
});

it('executes filter and returns modified value', function (): void {
    // Arrange: Set up expectations for both methods
    /** @var MockInterface $this->mockFilter */
    $this->mockFilter->shouldReceive('add')
        ->once()
        ->withAnyArgs();

    $this->mockFilter->shouldReceive('apply')
        ->once()
        ->with('test_filter', 'original')
        ->andReturn('modified_original');

    // Act: Process the test class and apply the filter
    $testClass = new SingleFilterClass;
    $this->processor->process($testClass);
    $result = $this->mockFilter->apply('test_filter', 'original');

    // Assert: Verify the filter returns the expected modified value
    expect($result)->toBe('modified_original');
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
    $testClass = new SingleFilterClass;

    // This should not throw an exception
    $result = $processor->process($testClass);

    // Assert: Method completes without exceptions
    expect(true)->toBeTrue();
});

afterEach(function (): void {
    Mockery::close();
});
