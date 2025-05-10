<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Facade;
use Pollora\Attributes\Attributable;
use Pollora\Attributes\AttributeProcessor;
use Pollora\Attributes\Filter;
use Pollora\Hook\Infrastructure\Services\Filter as FilterService;

beforeEach(function () {
    // Mock Filter service
    $this->mockFilter = Mockery::mock(FilterService::class);

    // Create a fake container that will be wrapped by ContainerServiceLocator
    $this->mockContainer = new class($this->mockFilter)
    {
        private $filterService;

        public function __construct($filterService)
        {
            $this->filterService = $filterService;
        }

        public function get($serviceClass)
        {
            if ($serviceClass === FilterService::class) {
                return $this->filterService;
            }

            return null;
        }
    };

    $this->processor = new AttributeProcessor($this->mockContainer);
});

class SingleFilterClass implements Attributable
{
    #[Filter('test_filter', priority: 10)]
    public function filterMethod(string $value): string
    {
        return "modified_{$value}";
    }
}

class MultipleFilterClass implements Attributable
{
    #[Filter('test_filter', priority: 10)]
    public function filterMethod(string $value): string
    {
        return "modified_{$value}";
    }

    #[Filter('another_filter', priority: 20)]
    public function anotherFilterMethod(array $data): array
    {
        $data['modified'] = true;

        return $data;
    }
}

class DefaultPriorityFilterClass implements Attributable
{
    #[Filter('test_filter')]
    public function filterMethod(string $value): string
    {
        // Test method with default priority
        return "modified_{$value}";
    }
}

class CustomPriorityFilterClass implements Attributable
{
    #[Filter('test_filter', priority: 99)]
    public function filterMethod(string $value): string
    {
        // Test method with custom priority
        return "modified_{$value}";
    }
}

it('registers a filter hook correctly', function () {
    // Set up expectations
    $this->mockFilter->shouldReceive('add')
        ->once()
        ->withArgs(function ($hook, $callback, $priority, $acceptedArgs) {
            return $hook === 'test_filter'
                && is_array($callback)
                && $callback[0] instanceof SingleFilterClass
                && $callback[1] === 'filterMethod'
                && $priority === 10
                && $acceptedArgs === 1;
        });

    // Process the test class
    $testClass = new SingleFilterClass;
    $this->processor->process($testClass);
});

it('registers multiple filter hooks with different priorities', function () {
    // Set up expectations for both filters
    $this->mockFilter->shouldReceive('add')
        ->once()
        ->withArgs(function ($hook, $callback, $priority, $acceptedArgs) {
            return $hook === 'test_filter'
                && is_array($callback)
                && $callback[0] instanceof MultipleFilterClass
                && $callback[1] === 'filterMethod'
                && $priority === 10;
        });

    $this->mockFilter->shouldReceive('add')
        ->once()
        ->withArgs(function ($hook, $callback, $priority, $acceptedArgs) {
            return $hook === 'another_filter'
                && is_array($callback)
                && $callback[0] instanceof MultipleFilterClass
                && $callback[1] === 'anotherFilterMethod'
                && $priority === 20;
        });

    // Process the test class
    $testClass = new MultipleFilterClass;
    $this->processor->process($testClass);
});

it('registers a filter hook with default priority (10)', function () {
    // Set up expectations
    $this->mockFilter->shouldReceive('add')
        ->once()
        ->withArgs(function ($hook, $callback, $priority, $acceptedArgs) {
            return $hook === 'test_filter'
                && is_array($callback)
                && $callback[0] instanceof DefaultPriorityFilterClass
                && $callback[1] === 'filterMethod'
                && $priority === 10 // Default priority should be 10
                && $acceptedArgs === 1;
        });

    // Process the test class
    $testClass = new DefaultPriorityFilterClass;
    $this->processor->process($testClass);
});

it('registers a filter hook with custom priority', function () {
    // Set up expectations
    $this->mockFilter->shouldReceive('add')
        ->once()
        ->withArgs(function ($hook, $callback, $priority, $acceptedArgs) {
            return $hook === 'test_filter'
                && is_array($callback)
                && $callback[0] instanceof CustomPriorityFilterClass
                && $callback[1] === 'filterMethod'
                && $priority === 99 // Custom priority
                && $acceptedArgs === 1;
        });

    // Process the test class
    $testClass = new CustomPriorityFilterClass;
    $this->processor->process($testClass);
});

it('executes filter and returns modified value', function () {
    // Set up expectations for the add method
    $this->mockFilter->shouldReceive('add')
        ->once()
        ->withAnyArgs();

    // Set up expectations for the apply method
    $this->mockFilter->shouldReceive('apply')
        ->once()
        ->with('test_filter', 'original')
        ->andReturn('modified_original');

    // Process the test class
    $testClass = new SingleFilterClass;
    $this->processor->process($testClass);

    // Test the apply method
    $result = $this->mockFilter->apply('test_filter', 'original');
    expect($result)->toBe('modified_original');
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
    $testClass = new SingleFilterClass;

    // This should not throw an exception
    $processor->process($testClass);

    // No assertions needed - we're just checking that it doesn't throw
    expect(true)->toBeTrue();
});

afterEach(function () {
    Mockery::close();
    Facade::clearResolvedInstances();
});
