<?php

declare(strict_types=1);

use Pollora\Discoverer\Domain\Models\DiscoveredClass;
use Pollora\Discoverer\Infrastructure\Repositories\InMemoryDiscoveryRegistry;

beforeEach(function () {
    $this->registry = new InMemoryDiscoveryRegistry;
});

test('registry can register and retrieve discovered classes', function () {
    $class1 = new DiscoveredClass('App\\Test\\Class1', 'type1');
    $class2 = new DiscoveredClass('App\\Test\\Class2', 'type1');
    $class3 = new DiscoveredClass('App\\Test\\Class3', 'type2');

    $this->registry->register($class1);
    $this->registry->register($class2);
    $this->registry->register($class3);

    expect($this->registry->getByType('type1'))->toHaveCount(2)
        ->and($this->registry->getByType('type2'))->toHaveCount(1)
        ->and($this->registry->getByType('nonexistent'))->toBeEmpty();
});

test('registry can check if a class is registered', function () {
    $class = new DiscoveredClass('App\\Test\\Class1', 'type1');
    $this->registry->register($class);

    expect($this->registry->has('App\\Test\\Class1'))->toBeTrue()
        ->and($this->registry->has('App\\Test\\Nonexistent'))->toBeFalse();
});

test('registry can return all registered classes', function () {
    $class1 = new DiscoveredClass('App\\Test\\Class1', 'type1');
    $class2 = new DiscoveredClass('App\\Test\\Class2', 'type2');

    $this->registry->register($class1);
    $this->registry->register($class2);

    $all = $this->registry->all();

    expect($all)->toHaveCount(2)
        ->and($all['type1'])->toHaveCount(1)
        ->and($all['type2'])->toHaveCount(1);
});
