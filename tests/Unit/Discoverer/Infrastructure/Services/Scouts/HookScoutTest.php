<?php

declare(strict_types=1);

use Illuminate\Contracts\Container\Container;
use Pollora\Application\Domain\Contracts\DebugDetectorInterface;
use Pollora\Discoverer\Infrastructure\Services\Scouts\HookScout;
use Pollora\Hook\Domain\Services\AbstractHook;
use Spatie\StructureDiscoverer\Cache\DiscoverCacheDriver;
use Spatie\StructureDiscoverer\Discover;

beforeEach(function () {
    $this->cacheDriver = Mockery::mock(DiscoverCacheDriver::class);
    $this->debugDetector = Mockery::mock(DebugDetectorInterface::class);

    // Create a mock container
    $this->app = Mockery::mock(Container::class);
    $this->app->shouldReceive('has')->andReturn(false);

    $this->scout = new HookScout(
        $this->app,
        [__DIR__, __DIR__.'/modules'] // Array of directories to scan
    );
});

afterEach(function () {
    Mockery::close();
});

test('getType returns hook type', function () {
    expect($this->scout->getType())->toBe('hook');
});

test('getDirectories returns valid directories only', function () {
    $directories = $this->scout->getDirectories();

    expect($directories)->toBeArray()
        ->and($directories)->toContain(__DIR__)
        ->and(count($directories))->toBeLessThanOrEqual(2); // Au maximum 2 chemins si modules existe
});

test('criteria applies AbstractHook class filter', function () {
    // Création d'un mock de Discover
    $discover = Mockery::mock(Discover::class);

    // Configuration du mock pour la chaîne d'appels
    $discover->shouldReceive('extending')
        ->once()
        ->with(AbstractHook::class)
        ->andReturnSelf();

    // Utilisation de la réflexion pour accéder à la méthode protégée
    $reflection = new ReflectionClass($this->scout);
    $method = $reflection->getMethod('criteria');
    $method->setAccessible(true);

    // Appel de la méthode criteria
    $result = $method->invokeArgs($this->scout, [$discover]);

    // Vérification que le résultat est bien le même objet
    expect($result)->toBe($discover);
});
