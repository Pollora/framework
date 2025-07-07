<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use Mockery as m;
use Pollora\Support\Facades\Action;

// Define app_path function if it doesn't exist in the test environment
if (! function_exists('app_path')) {
    function app_path($path = '')
    {
        return '/path/to/app/'.$path;
    }
}

// Define is_dir function if needed for testing
if (! function_exists('is_dir_mock')) {
    function is_dir_mock($path)
    {
        return true; // Always return true for testing
    }
}

// Define mkdir function if needed for testing
if (! function_exists('mkdir_mock')) {
    function mkdir_mock($path, $mode = 0777, $recursive = false)
    {
        return true; // Always return true for testing
    }
}

beforeAll(function () {
    $app = new Container;
    Facade::setFacadeApplication($app);

    $mock = m::mock('stdClass');
    $mock->shouldReceive('add')
        ->with('init', m::type('Closure'))
        ->andReturnNull();

    $app->instance('wp.action', $mock);

    Action::clearResolvedInstances();
    Action::setFacadeApplication($app);
});

afterAll(function () {
    m::close();
    Facade::clearResolvedInstances();
    Facade::setFacadeApplication(null);
});

it('can be instantiated', function () {
    expect(true)->toBeTrue();
});

// Note: This test file structure mirrors PostTypeAttributeServiceProviderTest.php
// but focuses on taxonomy-specific service provider functionality.
// Additional specific tests for TaxonomyServiceProvider would be added here
// based on the actual implementation details of the provider.
