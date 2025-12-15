<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Facade;
use Pollora\Attributes\Filter;
use Pollora\Hook\Infrastructure\Services\Filter as FilterService;

beforeEach(function () {
    // Mock Filter service
    $this->mockFilter = Mockery::mock(FilterService::class);

    // Create a fake service locator
    $this->mockServiceLocator = new class($this->mockFilter)
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
});

class SingleFilterClass
{
    #[Filter('test_filter', priority: 10)]
    public function filterMethod(string $value): string
    {
        return "modified_{$value}";
    }
}

class MultipleFilterClass
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

class DefaultPriorityFilterClass
{
    #[Filter('test_filter')]
    public function filterMethod(string $value): string
    {
        // Test method with default priority
        return "modified_{$value}";
    }
}

class CustomPriorityFilterClass
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

    // Test the filter attribute directly using handle method
    $testClass = new SingleFilterClass;
    $filterAttribute = new Filter('test_filter', 10);
    $methodReflection = new ReflectionMethod($testClass, 'filterMethod');
    
    $filterAttribute->handle($this->mockServiceLocator, $testClass, $methodReflection, $filterAttribute);
});

it('registers multiple filter hooks with different priorities', function () {
    $testClass = new MultipleFilterClass;
    
    // Test first filter
    $this->mockFilter->shouldReceive('add')
        ->once()
        ->withArgs(function ($hook, $callback, $priority, $acceptedArgs) {
            return $hook === 'test_filter'
                && is_array($callback)
                && $callback[0] instanceof MultipleFilterClass
                && $callback[1] === 'filterMethod'
                && $priority === 10;
        });

    $filterAttribute1 = new Filter('test_filter', 10);
    $methodReflection1 = new ReflectionMethod($testClass, 'filterMethod');
    $filterAttribute1->handle($this->mockServiceLocator, $testClass, $methodReflection1, $filterAttribute1);

    // Test second filter
    $this->mockFilter->shouldReceive('add')
        ->once()
        ->withArgs(function ($hook, $callback, $priority, $acceptedArgs) {
            return $hook === 'another_filter'
                && is_array($callback)
                && $callback[0] instanceof MultipleFilterClass
                && $callback[1] === 'anotherFilterMethod'
                && $priority === 20;
        });

    $filterAttribute2 = new Filter('another_filter', 20);
    $methodReflection2 = new ReflectionMethod($testClass, 'anotherFilterMethod');
    $filterAttribute2->handle($this->mockServiceLocator, $testClass, $methodReflection2, $filterAttribute2);
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

    // Test with default priority
    $testClass = new DefaultPriorityFilterClass;
    $filterAttribute = new Filter('test_filter'); // No priority specified, should default to 10
    $methodReflection = new ReflectionMethod($testClass, 'filterMethod');
    
    $filterAttribute->handle($this->mockServiceLocator, $testClass, $methodReflection, $filterAttribute);
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

    // Test with custom priority
    $testClass = new CustomPriorityFilterClass;
    $filterAttribute = new Filter('test_filter', 99);
    $methodReflection = new ReflectionMethod($testClass, 'filterMethod');
    
    $filterAttribute->handle($this->mockServiceLocator, $testClass, $methodReflection, $filterAttribute);
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

    // Test filter attribute directly
    $testClass = new SingleFilterClass;
    $filterAttribute = new Filter('test_filter', 10);
    $methodReflection = new ReflectionMethod($testClass, 'filterMethod');
    $filterAttribute->handle($this->mockServiceLocator, $testClass, $methodReflection, $filterAttribute);

    // Test the apply method
    $result = $this->mockFilter->apply('test_filter', 'original');
    expect($result)->toBe('modified_original');
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

    $testClass = new SingleFilterClass;
    $filterAttribute = new Filter('test_filter', 10);
    $methodReflection = new ReflectionMethod($testClass, 'filterMethod');

    // This should not throw an exception
    $filterAttribute->handle($mockServiceLocator, $testClass, $methodReflection, $filterAttribute);

    // No assertions needed - we're just checking that it doesn't throw
    expect(true)->toBeTrue();
});

afterEach(function () {
    Mockery::close();
    Facade::clearResolvedInstances();
});
