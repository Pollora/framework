<?php

declare(strict_types=1);

use Illuminate\Contracts\Container\Container;
use Pollora\Application\Domain\Contracts\DebugDetectorInterface;
use Pollora\Discoverer\Infrastructure\Services\SpatieDiscoveryAdapter;
use Spatie\StructureDiscoverer\Cache\DiscoverCacheDriver;
use Spatie\StructureDiscoverer\Discover;

// Création d'une classe concrète pour tester l'adaptateur abstrait
class TestSpatieDiscoveryAdapter extends SpatieDiscoveryAdapter
{
    private array $mockedDirectories;

    private string $mockedType;

    private ?Discover $criteriaResult;

    public bool $shouldUseCache = false;

    public function __construct(
        Container $app,
        array $mockedDirectories = [],
        string $mockedType = 'test_type',
        ?Discover $criteriaResult = null
    ) {
        parent::__construct($app);
        $this->mockedDirectories = $mockedDirectories;
        $this->mockedType = $mockedType;
        $this->criteriaResult = $criteriaResult;
    }

    public function getDirectories(): array
    {
        return $this->mockedDirectories;
    }

    public function getType(): string
    {
        return $this->mockedType;
    }

    protected function criteria(Discover $discover): Discover
    {
        // Si un résultat de critère est fourni, le retourner, sinon retourner le discoverer tel quel
        return $this->criteriaResult ?? $discover;
    }

    // Exposition des méthodes protégées pour les tests
    public function exposedGetCacheIdentifier(): string
    {
        return $this->getCacheIdentifier();
    }

    public function exposedShouldUseCache(): bool
    {
        return $this->shouldUseCache();
    }

    // Override de la méthode shouldUseCache pour les tests
    protected function shouldUseCache(): bool
    {
        if (isset($this->shouldUseCache) && $this->shouldUseCache) {
            return true;
        }

        return parent::shouldUseCache();
    }
}

beforeEach(function () {
    $this->cacheDriver = Mockery::mock(DiscoverCacheDriver::class);
    $this->debugDetector = Mockery::mock(DebugDetectorInterface::class);
    $this->debugDetector->shouldReceive('isDebugMode')->andReturn(false);

    // Create a mock container
    $this->app = Mockery::mock(Container::class);
    $this->app->shouldReceive('has')->with(DiscoverCacheDriver::class)->andReturn(true);
    $this->app->shouldReceive('make')->with(DiscoverCacheDriver::class)->andReturn($this->cacheDriver);
    $this->app->shouldReceive('has')->with(DebugDetectorInterface::class)->andReturn(true);
    $this->app->shouldReceive('make')->with(DebugDetectorInterface::class)->andReturn($this->debugDetector);

    $this->adapter = new TestSpatieDiscoveryAdapter(
        $this->app,
        [__DIR__], // Répertoire qui existe
        'test_type'
    );
});

afterEach(function () {
    Mockery::close();
});

test('discover returns empty array when no directories exist', function () {
    $app = Mockery::mock(Container::class);
    $app->shouldReceive('has')->andReturn(false);

    $adapter = new TestSpatieDiscoveryAdapter($app, ['/nonexistent/directory']);

    expect($adapter->discover())->toBeArray()
        ->and($adapter->discover())->toBeEmpty();
});

test('discover applies criteria to discovery instance', function () {
    // Mock qui va être retourné par criteria() pour nous permettre de vérifier
    $criteriaResult = Mockery::mock(Discover::class);
    $criteriaResult->shouldReceive('get')->once()->andReturn(['Class1', 'Class2']);

    $app = Mockery::mock(Container::class);
    $app->shouldReceive('has')->andReturn(false);

    $adapter = new TestSpatieDiscoveryAdapter(
        $app,
        [__DIR__], // Répertoire qui existe
        'test_type',
        $criteriaResult
    );

    expect($adapter->discover())->toBe(['Class1', 'Class2']);
});

test('discover applies cache when cache driver is provided and caching is enabled', function () {
    // Create a proper instance of Discover to mock
    $discoverInstance = Mockery::mock(Discover::class);
    $discoverInstance->shouldReceive('withCache')
        ->once()
        ->with('pollora_scout_test_type', $this->cacheDriver)
        ->andReturnSelf();

    $discoverInstance->shouldReceive('get')
        ->once()
        ->andReturn(['Class1']);

    // Create a new adapter with our mocked Discover instance
    $app = Mockery::mock(Container::class);
    $app->shouldReceive('has')->with(DiscoverCacheDriver::class)->andReturn(true);
    $app->shouldReceive('make')->with(DiscoverCacheDriver::class)->andReturn($this->cacheDriver);
    $app->shouldReceive('has')->with(DebugDetectorInterface::class)->andReturn(true);
    $app->shouldReceive('make')->with(DebugDetectorInterface::class)->andReturn($this->debugDetector);

    $adapter = new TestSpatieDiscoveryAdapter(
        $app,
        [__DIR__], // Répertoire qui existe
        'test_type',
        $discoverInstance
    );

    // Forcer l'activation du cache
    $adapter->shouldUseCache = true;

    expect($adapter->discover())->toBe(['Class1']);
});

test('cache identifier is correctly formatted', function () {
    expect($this->adapter->exposedGetCacheIdentifier())->toBe('pollora_scout_test_type');
});

test('cache is disabled in debug mode', function () {
    $debugDetector = Mockery::mock(DebugDetectorInterface::class);
    $debugDetector->shouldReceive('isDebugMode')->andReturn(true);

    $app = Mockery::mock(Container::class);
    $app->shouldReceive('has')->with(DiscoverCacheDriver::class)->andReturn(false);
    $app->shouldReceive('has')->with(DebugDetectorInterface::class)->andReturn(true);
    $app->shouldReceive('make')->with(DebugDetectorInterface::class)->andReturn($debugDetector);

    $adapter = new TestSpatieDiscoveryAdapter(
        $app,
        [__DIR__]
    );

    expect($adapter->exposedShouldUseCache())->toBeFalse();
});

test('cache is enabled when not in debug mode', function () {
    $debugDetector = Mockery::mock(DebugDetectorInterface::class);
    $debugDetector->shouldReceive('isDebugMode')->andReturn(false);

    $app = Mockery::mock(Container::class);
    $app->shouldReceive('has')->with(DiscoverCacheDriver::class)->andReturn(false);
    $app->shouldReceive('has')->with(DebugDetectorInterface::class)->andReturn(true);
    $app->shouldReceive('make')->with(DebugDetectorInterface::class)->andReturn($debugDetector);

    $adapter = new TestSpatieDiscoveryAdapter(
        $app,
        [__DIR__]
    );

    expect($adapter->exposedShouldUseCache())->toBeTrue();
});
