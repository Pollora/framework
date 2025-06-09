<?php

declare(strict_types=1);

use Pollora\Discoverer\Domain\Contracts\DiscoveryRegistryInterface;
use Pollora\Discoverer\Domain\Contracts\ScoutInterface;
use Pollora\Discoverer\Domain\Models\DiscoveredClass;
use Pollora\Discoverer\Domain\Services\DiscoveryService;

beforeEach(function () {
    // Mock dependencies
    $this->registry = Mockery::mock(DiscoveryRegistryInterface::class);
    $this->scout = Mockery::mock(ScoutInterface::class);

    // Service under test
    $this->service = new DiscoveryService($this->registry, [$this->scout]);
});

afterEach(function () {
    Mockery::close();
});

test('discoverAndRegister method discovers and registers classes from scouts', function () {
    // Mock configuration
    $this->scout->shouldReceive('getType')->andReturn('test_type');
    $this->scout->shouldReceive('discover')->once()->andReturn([
        'App\\Test\\Class1',
        'App\\Test\\Class2',
    ]);

    // Assertions for registering discovered classes
    $this->registry->shouldReceive('register')
        ->twice()
        ->withArgs(function (DiscoveredClass $class) {
            static $calls = 0;
            $calls++;

            if ($calls === 1) {
                return $class->getClassName() === 'App\\Test\\Class1' &&
                       $class->getType() === 'test_type';
            }

            if ($calls === 2) {
                return $class->getClassName() === 'App\\Test\\Class2' &&
                       $class->getType() === 'test_type';
            }

            return false;
        });

    // Execute the method under test
    $this->service->discoverAndRegister();
});

test('getByType method delegates to registry', function () {
    $this->registry->shouldReceive('getByType')
        ->once()
        ->with('test_type')
        ->andReturn(['test_result']);

    expect($this->service->getByType('test_type'))->toBe(['test_result']);
});

test('hasDiscovered method delegates to registry', function () {
    $this->registry->shouldReceive('has')
        ->once()
        ->with('App\\Test\\Class1')
        ->andReturn(true);

    expect($this->service->hasDiscovered('App\\Test\\Class1'))->toBeTrue();
});

test('getAllDiscovered method delegates to registry', function () {
    $expected = ['type1' => ['class1'], 'type2' => ['class2']];

    $this->registry->shouldReceive('all')
        ->once()
        ->andReturn($expected);

    expect($this->service->getAllDiscovered())->toBe($expected);
});
