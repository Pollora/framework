<?php

declare(strict_types=1);

use Pollora\Discoverer\Domain\Contracts\DiscoveryRegistryInterface;
use Pollora\Discoverer\Domain\Contracts\DiscoveryRepositoryInterface;
use Pollora\Discoverer\Domain\Models\DiscoveredClass;
use Pollora\Discoverer\Domain\Services\DiscoveryRepositoryService;

beforeEach(function () {
    // Mock des dépendances
    $this->repository = Mockery::mock(DiscoveryRepositoryInterface::class);
    $this->registry = Mockery::mock(DiscoveryRegistryInterface::class);

    // Service à tester
    $this->service = new DiscoveryRepositoryService($this->repository, $this->registry);
});

afterEach(function () {
    Mockery::close();
});

test('storeClass method creates and stores DiscoveredClass', function () {
    // Assertion pour vérifier que la classe est correctement stockée
    $this->repository->shouldReceive('store')
        ->once()
        ->withArgs(function (DiscoveredClass $class) {
            return $class->getClassName() === 'App\\Test\\Class1' &&
                   $class->getType() === 'test_type';
        });

    // Exécution de la méthode à tester
    $this->service->storeClass('App\\Test\\Class1', 'test_type');
});

test('getByType method delegates to repository', function () {
    $this->repository->shouldReceive('findByType')
        ->once()
        ->with('test_type')
        ->andReturn(['test_result']);

    expect($this->service->getByType('test_type'))->toBe(['test_result']);
});

test('exists method delegates to repository', function () {
    $this->repository->shouldReceive('exists')
        ->once()
        ->with('App\\Test\\Class1')
        ->andReturn(true);

    expect($this->service->exists('App\\Test\\Class1'))->toBeTrue();
});

test('persist method delegates to repository', function () {
    $this->repository->shouldReceive('persist')
        ->once();

    $this->service->persist();
});

test('getAll method delegates to repository', function () {
    $expected = ['type1' => ['class1'], 'type2' => ['class2']];

    $this->repository->shouldReceive('all')
        ->once()
        ->andReturn($expected);

    expect($this->service->getAll())->toBe($expected);
});

test('clear method delegates to repository', function () {
    $this->repository->shouldReceive('clear')
        ->once();

    $this->service->clear();
});

test('syncFromRegistry copies classes from registry to repository', function () {
    // Configuration des mocks
    $class1 = new DiscoveredClass('App\\Test\\Class1', 'type1');
    $class2 = new DiscoveredClass('App\\Test\\Class2', 'type2');

    $registryData = [
        'type1' => [$class1],
        'type2' => [$class2],
    ];

    $this->registry->shouldReceive('all')
        ->once()
        ->andReturn($registryData);

    // Assertions pour les appels à store
    $this->repository->shouldReceive('store')
        ->once()
        ->with($class1);

    $this->repository->shouldReceive('store')
        ->once()
        ->with($class2);

    // Assert que persist est appelé à la fin
    $this->repository->shouldReceive('persist')
        ->once();

    // Exécution de la méthode à tester
    $this->service->syncFromRegistry();
});
