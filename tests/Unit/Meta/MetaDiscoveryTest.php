<?php

declare(strict_types=1);

use Pollora\Attributes\Meta;
use Pollora\Discovery\Domain\Models\DiscoveryLocation;
use Pollora\Meta\Application\Services\MetaSchemaBuilder;
use Pollora\Meta\Application\Services\MetaSchemaRepository;
use Pollora\Meta\Domain\Contracts\MetaRegistryInterface;
use Pollora\Meta\Domain\Models\MetaSchema;
use Pollora\Meta\Infrastructure\Services\MetaDiscovery;
use Psr\Log\LoggerInterface;
use Spatie\StructureDiscoverer\Data\DiscoveredClass;
use Spatie\StructureDiscoverer\Data\DiscoveredEnum;
use Tests\Unit\Meta\Fixtures\BookGenre;
use Tests\Unit\Meta\Fixtures\Event;
use Tests\Unit\Meta\Fixtures\EventExtras;
use Tests\Unit\Meta\Fixtures\EventStatus;
use Tests\Unit\Meta\Fixtures\InvalidArray;
use Tests\Unit\Meta\Fixtures\NoMeta;
use Tests\Unit\Meta\Fixtures\NotADeclaration;

require_once __DIR__.'/Fixtures/Invalid.php';

abstract class AbstractMetaDeclaration
{
    #[Meta]
    public string $value = '';
}

beforeEach(function (): void {
    $this->registry = Mockery::mock(MetaRegistryInterface::class);
    $this->schemas = new MetaSchemaRepository;
    $this->logger = Mockery::mock(LoggerInterface::class);
    $this->discovery = new MetaDiscovery(new MetaSchemaBuilder, $this->schemas, $this->registry, $this->logger);
    $this->location = new DiscoveryLocation('Tests\\', __DIR__);

    $this->discover = function (string ...$classes): void {
        foreach ($classes as $class) {
            $this->discovery->discover($this->location, DiscoveredClass::fromReflection(new ReflectionClass($class)));
        }
    };
});

it('keeps post type and taxonomy classes only', function (): void {
    ($this->discover)(Event::class, BookGenre::class, NotADeclaration::class, AbstractMetaDeclaration::class);
    $this->discovery->discover($this->location, DiscoveredEnum::fromReflection(new ReflectionEnum(EventStatus::class)));

    expect(array_column(iterator_to_array($this->discovery->getItems()), 'class'))->toBe([Event::class, BookGenre::class]);
});

it('stores and registers the schema of each declaring class', function (): void {
    $registered = [];
    $this->registry->shouldReceive('register')->andReturnUsing(function (MetaSchema $schema) use (&$registered): void {
        $registered[] = $schema->declaringClass;
    });
    ($this->discover)(Event::class, BookGenre::class, NoMeta::class);

    $this->discovery->apply();

    expect($registered)->toBe([Event::class, BookGenre::class])
        ->and($this->schemas->forClass(Event::class))->toBeInstanceOf(MetaSchema::class)
        ->and($this->schemas->forClass(NoMeta::class))->toBeNull();
});

it('registers each schema once when applied again', function (): void {
    $this->registry->shouldReceive('register')->once();
    ($this->discover)(Event::class);

    $this->discovery->apply();
    $this->discovery->apply();
});

it('logs a declaration it cannot register and carries on', function (): void {
    $this->registry->shouldReceive('register')->twice();
    $this->logger->shouldReceive('error')->once()->with(Mockery::pattern('/InvalidArray: .*the type array is not supported yet/'), Mockery::type('array'));
    $this->logger->shouldReceive('error')->once()->with(Mockery::pattern('/EventExtras: The meta key "capacity" of "event" is declared twice/'), Mockery::type('array'));
    ($this->discover)(Event::class, InvalidArray::class, EventExtras::class, BookGenre::class);

    $this->discovery->apply();
});

it('identifies itself as meta', function (): void {
    expect($this->discovery->getIdentifier())->toBe('meta');
});
