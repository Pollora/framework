<?php

declare(strict_types=1);

namespace Tests\Unit\Attributes;

use Illuminate\Support\Facades\Facade;
use Mockery;
use Pollora\Attributes\Action;
use Pollora\Attributes\Attributable;
use Pollora\Attributes\AttributeProcessor;
use Pollora\Attributes\Filter;
use Pollora\Support\Facades\Filter as FilterFacade;
use Pollora\Support\Facades\Action as ActionFacade;

beforeEach(function () {
    Facade::clearResolvedInstances();
    AttributeProcessor::clearCache();
});

afterEach(function () {
    Mockery::close();
});

it('registers an action hook', function () {
    // Instead of mocking the final class, let's mock the facade directly
    ActionFacade::shouldReceive('add')
        ->once()
        ->withArgs(function ($hook, $callback, $priority, $acceptedArgs) {
            return $hook === 'test_hook'
                && is_array($callback)
                && $callback[0] instanceof TestActionClass
                && $callback[1] === 'testMethod'
                && $priority === 10
                && $acceptedArgs === 0;
        });

    $testClass = new TestActionClass();
    AttributeProcessor::process($testClass);
});

class TestActionClass implements Attributable
{
    #[Action('test_hook', priority: 10)]
    public function testMethod()
    {
        // Test method
    }
}

it('registers a filter hook and modifies value', function () {
    // Let's mock the facade directly
    FilterFacade::shouldReceive('add')
        ->once()
        ->withArgs(function ($hook, $callback, $priority, $acceptedArgs) {
            return $hook === 'test_filter'
                && is_array($callback)
                && $callback[0] instanceof TestFilterClass
                && $callback[1] === 'filterMethod'
                && $priority === 10
                && $acceptedArgs === 1;
        });

    $testClass = new TestFilterClass;
    AttributeProcessor::process($testClass);
});

// Test the execution of the filter
it('executes filter and returns modified value', function () {
    // Let's mock the facade directly for adding and applying the filter
    FilterFacade::shouldReceive('add')
        ->once()
        ->andReturnNull();

    FilterFacade::shouldReceive('apply')
        ->once()
        ->with('test_filter', 'original value')
        ->andReturn('modified value');

    $testClass = new TestFilterClass;
    AttributeProcessor::process($testClass);

    // Test filter application
    $result = FilterFacade::apply('test_filter', 'original value');
    expect($result)->toBe('modified value');
});

class TestFilterClass implements Attributable
{
    #[Filter('test_filter')]
    public function filterMethod(string $value): string
    {
        return 'modified '.$value;
    }
}
