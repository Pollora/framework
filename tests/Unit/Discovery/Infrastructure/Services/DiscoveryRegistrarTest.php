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
use Spatie\StructureDiscoverer\Data\DiscoveredClass;
use Spatie\StructureDiscoverer\Data\DiscoveredStructure;

function createDiscoveredClassStructure(
    string $name,
    string $namespace,
    array $implements = [],
    ?array $implementsChain = null,
    bool $isAbstract = false,
): DiscoveredClass {
    return new DiscoveredClass(
        name: $name,
        file: '/tmp/'.$name.'.php',
        namespace: $namespace,
        isFinal: false,
        isAbstract: $isAbstract,
        isReadonly: false,
        extends: null,
        implements: $implements,
        attributes: [],
        implementsChain: $implementsChain,
    );
}

function createMockEngine(): DiscoveryEngineInterface
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

    it('registers a discovery found in structures', function (): void {
        // Create a fake Discovery implementation
        $fakeDiscovery = new class implements DiscoveryInterface
        {
            private DiscoveryItemsInterface $items;

            public function __construct()
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
                return 'fake_discovery';
            }
        };

        $fakeClass = $fakeDiscovery::class;
        $this->container->instance($fakeClass, $fakeDiscovery);

        // Create a structure that implements DiscoveryInterface
        $parts = explode('\\', $fakeClass);
        $name = array_pop($parts);
        $namespace = implode('\\', $parts);

        $structure = createDiscoveredClassStructure(
            name: $name,
            namespace: $namespace,
            implements: [DiscoveryInterface::class],
        );

        $engine = createMockEngine();
        $engine->shouldReceive('addDiscovery')
            ->once()
            ->with('fake_discovery', $fakeDiscovery);

        $registered = $this->registrar->registerFromStructures([$structure], $engine);

        expect($registered)->toBe(['fake_discovery']);
    });

    it('skips abstract classes', function (): void {
        $structure = createDiscoveredClassStructure(
            name: 'AbstractDiscovery',
            namespace: 'App',
            implements: [DiscoveryInterface::class],
            isAbstract: true,
        );

        $engine = createMockEngine();
        $engine->shouldNotReceive('addDiscovery');

        $registered = $this->registrar->registerFromStructures([$structure], $engine);

        expect($registered)->toBe([]);
    });

    it('skips classes that do not implement DiscoveryInterface', function (): void {
        $structure = createDiscoveredClassStructure(
            name: 'RegularClass',
            namespace: 'App',
            implements: [],
        );

        $engine = createMockEngine();
        $engine->shouldNotReceive('addDiscovery');

        $registered = $this->registrar->registerFromStructures([$structure], $engine);

        expect($registered)->toBe([]);
    });

    it('skips already registered discoveries (manual takes precedence)', function (): void {
        $fakeDiscovery = Mockery::mock(DiscoveryInterface::class);
        $fakeDiscovery->shouldReceive('getIdentifier')->andReturn('already_registered');

        $fakeClass = $fakeDiscovery::class;
        $this->container->instance($fakeClass, $fakeDiscovery);

        $parts = explode('\\', $fakeClass);
        $name = array_pop($parts);
        $namespace = implode('\\', $parts);

        $structure = createDiscoveredClassStructure(
            name: $name,
            namespace: $namespace,
            implements: [DiscoveryInterface::class],
        );

        // Engine already has this identifier registered
        $engine = Mockery::mock(DiscoveryEngineInterface::class);
        $existingDiscoveries = new Collection(['already_registered' => $fakeDiscovery]);
        $engine->shouldReceive('getDiscoveries')->andReturn($existingDiscoveries);
        $engine->shouldNotReceive('addDiscovery');

        $registered = $this->registrar->registerFromStructures([$structure], $engine);

        expect($registered)->toBe([]);
    });

    it('detects DiscoveryInterface via implementsChain', function (): void {
        $fakeDiscovery = new class implements DiscoveryInterface
        {
            private DiscoveryItemsInterface $items;

            public function __construct()
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
                return 'chain_discovery';
            }
        };

        $fakeClass = $fakeDiscovery::class;
        $this->container->instance($fakeClass, $fakeDiscovery);

        $parts = explode('\\', $fakeClass);
        $name = array_pop($parts);
        $namespace = implode('\\', $parts);

        $structure = createDiscoveredClassStructure(
            name: $name,
            namespace: $namespace,
            implements: [], // Not in direct implements
            implementsChain: [DiscoveryInterface::class], // But in the chain
        );

        $engine = createMockEngine();
        $engine->shouldReceive('addDiscovery')
            ->once()
            ->with('chain_discovery', $fakeDiscovery);

        $registered = $this->registrar->registerFromStructures([$structure], $engine);

        expect($registered)->toBe(['chain_discovery']);
    });

    it('gracefully handles unresolvable classes', function (): void {
        $structure = createDiscoveredClassStructure(
            name: 'NonExistentDiscovery',
            namespace: 'App\\Missing',
            implements: [DiscoveryInterface::class],
        );

        $engine = createMockEngine();
        $engine->shouldNotReceive('addDiscovery');

        $registered = $this->registrar->registerFromStructures([$structure], $engine);

        expect($registered)->toBe([]);
    });
});
