<?php

declare(strict_types=1);

use Pollora\Attributes\Taxonomy;
use Pollora\Attributes\Taxonomy\ZzDiscoveryBrokenOnClass;
use Pollora\Attributes\Taxonomy\ZzDiscoveryBrokenOnMethod;
use Pollora\Attributes\Taxonomy\ZzDiscoveryHandlesClass;
use Pollora\Attributes\Taxonomy\ZzDiscoveryHandlesMethod;
use Pollora\Discovery\Domain\Models\DiscoveryLocation;
use Pollora\Discovery\Infrastructure\Services\ReflectionCache;
use Pollora\Taxonomy\Domain\Contracts\TaxonomyServiceInterface;
use Pollora\Taxonomy\Infrastructure\Services\TaxonomyDiscovery;
use Psr\Log\LoggerInterface;
use Spatie\StructureDiscoverer\Data\DiscoveredClass;

require_once __DIR__.'/Fixtures/DiscoveryAttributes.php';

#[Taxonomy('handled')]
#[ZzDiscoveryHandlesClass]
final class HandledByItsAttributesTaxonomy
{
    #[ZzDiscoveryHandlesMethod]
    public function handledMethod(): void {}
}

#[Taxonomy('broken-class-attr')]
#[ZzDiscoveryBrokenOnClass]
final class BrokenClassAttributeTaxonomy {}

#[Taxonomy('broken-method-attr')]
final class BrokenMethodAttributeTaxonomy
{
    #[ZzDiscoveryBrokenOnMethod]
    public function broken(): void {}
}

/**
 * @return list<array<string, mixed>> The arguments each registered taxonomy was given
 */
function handlersRunTaxonomyDiscovery(string $class, ?LoggerInterface $logger = null, bool $withCache = true): array
{
    $registered = [];
    $service = Mockery::mock(TaxonomyServiceInterface::class);
    $service->shouldReceive('register')->andReturnUsing(function (string $slug, string|array $objectType, string $singular, string $plural, array $args) use (&$registered): object {
        $registered[] = $args;

        return new stdClass;
    });

    $discovery = new TaxonomyDiscovery($service, $logger);
    $discovery->discover(new DiscoveryLocation('Tests\\', __DIR__), DiscoveredClass::fromReflection(new ReflectionClass($class)), $withCache ? new ReflectionCache : null);
    $discovery->apply();

    return $registered;
}

describe('TaxonomyDiscovery attribute handlers', function (): void {
    it('lets an attribute that handles itself change the arguments, at class level and at method level', function (): void {
        $registered = handlersRunTaxonomyDiscovery(HandledByItsAttributesTaxonomy::class);

        expect($registered)->toHaveCount(1)
            ->and($registered[0])->toMatchArray(['handled_class' => 'HandledByItsAttributesTaxonomy', 'handled_method' => 'handledMethod']);
    });

    it('logs a class-level attribute that cannot be built and still registers the taxonomy', function (): void {
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('error')->once()->with(Mockery::on(fn (string $m): bool => str_contains($m, 'class-level attributes for \\'.BrokenClassAttributeTaxonomy::class)), Mockery::type('array'));

        expect(handlersRunTaxonomyDiscovery(BrokenClassAttributeTaxonomy::class, $logger))->toHaveCount(1);
    });

    it('logs a method-level attribute that cannot be built and still registers the taxonomy', function (): void {
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('error')->once()->with(Mockery::on(fn (string $m): bool => str_contains($m, 'method-level attributes for \\'.BrokenMethodAttributeTaxonomy::class)), Mockery::type('array'));

        expect(handlersRunTaxonomyDiscovery(BrokenMethodAttributeTaxonomy::class, $logger))->toHaveCount(1);
    });

    it('logs the class and registers nothing when discovery had no reflection cache', function (): void {
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('error')->once()->with(Mockery::on(fn (string $m): bool => str_contains($m, 'Failed to process Taxonomy for class \\'.HandledByItsAttributesTaxonomy::class)), Mockery::type('array'));

        expect(handlersRunTaxonomyDiscovery(HandledByItsAttributesTaxonomy::class, $logger, false))->toBe([]);
    });

    it('builds a configurable entity with the extra arguments it is given', function (): void {
        $discovery = new TaxonomyDiscovery(Mockery::mock(TaxonomyServiceInterface::class));

        $entity = $discovery->createEntityForConfiguring('film', null, null, ['menu_position' => 12], 7);

        expect($entity->getSlug())->toBe('film')
            ->and($entity->getArgs())->toMatchArray(['menu_position' => 12]);
    });
});
