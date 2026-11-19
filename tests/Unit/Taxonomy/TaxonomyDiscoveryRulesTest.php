<?php

declare(strict_types=1);

use Brain\Monkey\Functions;
use Pollora\Attributes\Taxonomy;
use Pollora\Attributes\Taxonomy\Hierarchical;
use Pollora\Discovery\Domain\Models\DiscoveryLocation;
use Pollora\Discovery\Infrastructure\Services\ReflectionCache;
use Pollora\Taxonomy\Domain\Contracts\TaxonomyServiceInterface;
use Pollora\Taxonomy\Infrastructure\Services\TaxonomyDiscovery;
use Pollora\Translation\Infrastructure\Adapters\TranslatingEntityRegistry;
use Psr\Log\LoggerInterface;
use Spatie\StructureDiscoverer\Data\DiscoveredClass;
use Spatie\StructureDiscoverer\Data\DiscoveredInterface;

#[Taxonomy]
final class ContentBookShelf {}

#[Taxonomy('film-genre', singular: 'Genre', plural: 'Genres', objectType: ['film', 'show'], textDomain: 'shop')]
final class ContentFilmGenre
{
    /** @return array<string, mixed> */
    public function withArgs(): array
    {
        return ['rewrite' => ['slug' => 'genres']];
    }
}

#[Taxonomy('shelf', singular: 'Shelf', plural: 'Shelves', objectType: 'book')]
#[Hierarchical]
final class ContentConfiguredShelf
{
    public function configuring(object $taxonomy): void
    {
        $taxonomy->setRawArgs(['show_admin_column' => true]);
    }
}

#[Taxonomy('abstracts')]
abstract class ContentAbstractTaxonomy {}

final class ContentUndecoratedTaxonomy {}

#[Taxonomy('boom')]
final class ContentExplodingTaxonomy
{
    public function __construct()
    {
        throw new RuntimeException('cannot build');
    }
}

/**
 * @return array{list<array<string, mixed>>, TaxonomyDiscovery}
 */
function contentRunTaxonomyDiscovery(array $classes, ?LoggerInterface $logger = null): array
{
    $registered = [];
    $service = Mockery::mock(TaxonomyServiceInterface::class);
    $service->shouldReceive('register')->andReturnUsing(
        function (string $slug, string|array $objectType, string $singular, string $plural, array $args, int $priority) use (&$registered): object {
            $registered[] = ['slug' => $slug, 'objectType' => $objectType, 'singular' => $singular, 'plural' => $plural, 'args' => $args, 'priority' => $priority];

            return new stdClass;
        }
    );

    $discovery = new TaxonomyDiscovery($service, $logger);
    $location = new DiscoveryLocation('Tests\\', __DIR__);

    foreach ($classes as $class) {
        $structure = is_string($class) ? DiscoveredClass::fromReflection(new ReflectionClass($class)) : $class;
        $discovery->discover($location, $structure, new ReflectionCache);
    }

    $discovery->apply();

    return [$registered, $discovery];
}

describe('TaxonomyDiscovery rules', function (): void {
    it('identifies itself as the taxonomy discovery', function (): void {
        $discovery = new TaxonomyDiscovery(Mockery::mock(TaxonomyServiceInterface::class));

        expect($discovery->getIdentifier())->toBe('taxonomies');
    });

    it('names a taxonomy from its class and attaches it to posts when the attribute says nothing', function (): void {
        [$registered] = contentRunTaxonomyDiscovery([ContentBookShelf::class]);

        expect($registered)->toHaveCount(1)
            ->and($registered[0]['slug'])->toBe('content-book-shelf')
            ->and($registered[0]['objectType'])->toBe(['post'])
            ->and($registered[0]['singular'])->toBe('Content book shelf')
            ->and($registered[0]['plural'])->toBe('Content book shelves')
            ->and($registered[0]['priority'])->toBe(5)
            ->and($registered[0]['args']['labels'])->toBe([TranslatingEntityRegistry::DEFAULT_LABELS => ['taxonomy', 'Content book shelf', 'Content book shelves']])
            ->and($registered[0]['args']['text_domain'])->toBe('pollora');
    });

    it('keeps the object types and the text domain the attribute declares', function (): void {
        [$registered] = contentRunTaxonomyDiscovery([ContentFilmGenre::class]);

        expect($registered[0]['objectType'])->toBe(['film', 'show'])
            ->and($registered[0]['args']['text_domain'])->toBe('shop');
    });

    it('adds the arguments the class returns from withArgs', function (): void {
        [$registered] = contentRunTaxonomyDiscovery([ContentFilmGenre::class]);

        expect($registered[0]['args']['rewrite'])->toBe(['slug' => 'genres'])
            ->and($registered[0]['args']['labels'])->toBe([TranslatingEntityRegistry::DEFAULT_LABELS => ['taxonomy', 'Genre', 'Genres']]);
    });

    it('only picks up concrete classes that carry the attribute', function (): void {
        [$registered] = contentRunTaxonomyDiscovery([
            ContentAbstractTaxonomy::class,
            ContentUndecoratedTaxonomy::class,
            ContentFilmGenre::class,
            new DiscoveredInterface('Thing', __FILE__, 'Tests', [], []),
        ]);

        expect(array_column($registered, 'slug'))->toBe(['film-genre']);
    });

    it('registers a class with configuring through the entity, at init', function (): void {
        $hooks = [];
        Functions\when('add_action')->alias(function (string $hook, callable $callback, int $priority = 10) use (&$hooks): bool {
            $hooks[] = [$hook, $callback, $priority];

            return true;
        });

        [$viaService] = contentRunTaxonomyDiscovery([ContentConfiguredShelf::class]);

        $entity = (new ReflectionFunction($hooks[0][1]))->getClosureUsedVariables()['entity'];

        expect($viaService)->toBe([])
            ->and($hooks)->toHaveCount(1)
            ->and($hooks[0][0])->toBe('init')
            ->and($hooks[0][2])->toBe(5)
            ->and($entity->getSlug())->toBe('shelf')
            ->and($entity->getArgs())->toMatchArray(['show_admin_column' => true, 'hierarchical' => true]);
    });

    it('logs a class it cannot build and keeps registering the others', function (): void {
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('error')->atLeast()->once()->withArgs(
            fn (string $message, array $context): bool => str_contains($message, ContentExplodingTaxonomy::class)
                && $context['exception'] instanceof Throwable
        );

        [$registered] = contentRunTaxonomyDiscovery([ContentExplodingTaxonomy::class, ContentFilmGenre::class], $logger);

        expect(array_column($registered, 'slug'))->toContain('film-genre');
    });
});
