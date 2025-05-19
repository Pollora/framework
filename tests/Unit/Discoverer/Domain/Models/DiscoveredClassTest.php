<?php

declare(strict_types=1);

use Pollora\Discoverer\Domain\Models\DiscoveredClass;

test('discovered class can be created with class name and type', function () {
    $discoveredClass = new DiscoveredClass('App\\Test\\MyClass', 'test_type');

    expect($discoveredClass->getClassName())->toBe('App\\Test\\MyClass')
        ->and($discoveredClass->getType())->toBe('test_type');
});

test('discovered class is immutable', function () {
    $discoveredClass = new DiscoveredClass('App\\Test\\MyClass', 'test_type');

    expect($discoveredClass)->toBeInstanceOf(DiscoveredClass::class)
        ->and($discoveredClass->getClassName())->toBe('App\\Test\\MyClass')
        ->and($discoveredClass->getType())->toBe('test_type');
});
