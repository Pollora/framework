<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Support\Collection;
use Pollora\Discovery\Domain\Contracts\DiscoveryEngineInterface;
use Pollora\Discovery\Domain\Contracts\DiscoveryInterface;
use Pollora\Discovery\Domain\Contracts\DiscoveryItemsInterface;
use Pollora\Discovery\Domain\Contracts\DiscoveryLocationInterface;
use Pollora\Discovery\Domain\Contracts\ReflectionCacheInterface;
use Pollora\Discovery\Domain\Models\DiscoveryItems;
use Pollora\Discovery\Infrastructure\Services\DiscoveryRegistrar;
use Spatie\StructureDiscoverer\Data\DiscoveredStructure;

function createFakeDiscovery(string $identifier): DiscoveryInterface
{
    return new class($identifier) implements DiscoveryInterface
    {
        private DiscoveryItemsInterface $items;

        public function __construct(private readonly string $id)
        {
            $this->items = new DiscoveryItems;
        }

        public function discover(DiscoveryLocationInterface $location, DiscoveredStructure $structure, ?ReflectionCacheInterface $reflectionCache = null): void {}

        public function getItems(): DiscoveryItemsInterface
        {
            return $this->items;
        }

        public function setItems(DiscoveryItemsInterface $items): void
        {
            $this->items = $items;
        }

        public function apply(): void {}

        public function getIdentifier(): string
        {
            return $this->id;
        }
    };
}

function createMockRegistrarEngine(): DiscoveryEngineInterface
{
    $engine = Mockery::mock(DiscoveryEngineInterface::class);
    $engine->shouldReceive('getDiscoveries')
        ->andReturn(new Collection);

    return $engine;
}

describe('DiscoveryRegistrar', function (): void {

    beforeEach(function (): void {
        $this->container = new Container;
        Container::setInstance($this->container);
        $this->registrar = new DiscoveryRegistrar($this->container);
    });

    it('registers a discovery found in container bindings', function (): void {
        $discovery = createFakeDiscovery('test_discovery');
        $this->container->singleton('App\\TestDiscovery', fn () => $discovery);

        $engine = createMockRegistrarEngine();
        $engine->shouldReceive('addDiscovery')
            ->once()
            ->with('test_discovery', $discovery);

        $registered = $this->registrar->registerFromContainer($engine);

        expect($registered)->toContain('test_discovery');
    });

    it('only checks bindings ending with Discovery', function (): void {
        $this->container->singleton('App\\SomeService', fn () => new stdClass);

        $engine = createMockRegistrarEngine();
        $engine->shouldNotReceive('addDiscovery');

        $registered = $this->registrar->registerFromContainer($engine);

        expect($registered)->toBe([]);
    });

    it('skips already registered discoveries', function (): void {
        $discovery = createFakeDiscovery('already_registered');
        $this->container->singleton('App\\AlreadyRegisteredDiscovery', fn () => $discovery);

        $engine = Mockery::mock(DiscoveryEngineInterface::class);
        $engine->shouldReceive('getDiscoveries')
            ->andReturn(new Collection(['already_registered' => $discovery]));
        $engine->shouldNotReceive('addDiscovery');

        $registered = $this->registrar->registerFromContainer($engine);

        expect($registered)->toBe([]);
    });

    it('gracefully handles unresolvable bindings', function (): void {
        $this->container->singleton('App\\BrokenDiscovery', fn () => throw new RuntimeException('Cannot resolve'));

        $engine = createMockRegistrarEngine();
        $engine->shouldNotReceive('addDiscovery');

        $registered = $this->registrar->registerFromContainer($engine);

        expect($registered)->toBe([]);
    });

    it('skips non-DiscoveryInterface instances', function (): void {
        $this->container->singleton('App\\FakeDiscovery', fn () => new stdClass);

        $engine = createMockRegistrarEngine();
        $engine->shouldNotReceive('addDiscovery');

        $registered = $this->registrar->registerFromContainer($engine);

        expect($registered)->toBe([]);
    });
});
