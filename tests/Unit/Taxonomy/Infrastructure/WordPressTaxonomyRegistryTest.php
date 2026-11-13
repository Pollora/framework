<?php

declare(strict_types=1);

use Brain\Monkey\Functions;
use Pollora\Taxonomy\Infrastructure\Adapters\WordPressTaxonomyRegistry;

function contentTaxonomyEntity(string $slug, array $args = [], string|array|null $objectType = null): object
{
    return new class($slug, $args, $objectType)
    {
        public function __construct(
            private readonly string $slug,
            private readonly array $args,
            private readonly string|array|null $objectType,
        ) {}

        public function getSlug(): string
        {
            return $this->slug;
        }

        public function getArgs(): array
        {
            return $this->args;
        }

        public function getObjectType(): string|array|null
        {
            return $this->objectType;
        }

        public function getSingularName(): string
        {
            return 'Genre';
        }

        public function getPluralName(): string
        {
            return 'Genres';
        }
    };
}

describe('WordPressTaxonomyRegistry', function (): void {
    it('hands the slug, the object types and the arguments to register_taxonomy', function (): void {
        $captured = null;
        Functions\expect('register_taxonomy')->once()->andReturnUsing(function ($slug, $objectType, $args) use (&$captured) {
            $captured = [$slug, $objectType, $args];

            return new stdClass;
        });

        $registered = (new WordPressTaxonomyRegistry)->register(contentTaxonomyEntity('genre', ['hierarchical' => true], ['book', 'movie']));

        expect($registered)->toBeTrue()
            ->and($captured[0])->toBe('genre')
            ->and($captured[1])->toBe(['book', 'movie'])
            ->and($captured[2]['hierarchical'])->toBeTrue();
    });

    it('attaches the taxonomy to posts when the entity has no object type method', function (): void {
        $objectType = null;
        Functions\expect('register_taxonomy')->once()->andReturnUsing(function ($slug, $type) use (&$objectType) {
            $objectType = $type;

            return new stdClass;
        });

        $entity = new class
        {
            public function getSlug(): string
            {
                return 'genre';
            }
        };

        expect((new WordPressTaxonomyRegistry)->register($entity))->toBeTrue()
            ->and($objectType)->toBe('post');
    });

    it('generates the labels from the singular and plural names when none are given', function (): void {
        $captured = null;
        Functions\expect('register_taxonomy')->once()->andReturnUsing(function ($slug, $type, $args) use (&$captured) {
            $captured = $args;

            return new stdClass;
        });

        (new WordPressTaxonomyRegistry)->register(contentTaxonomyEntity('genre'));

        expect($captured['labels'])->toMatchArray([
            'name' => 'Genres',
            'singular_name' => 'Genre',
            'menu_name' => 'Genres',
            'all_items' => 'All Genres',
            'edit_item' => 'Edit Genre',
            'add_new_item' => 'Add New Genre',
            'search_items' => 'Search Genres',
            'separate_items_with_commas' => 'Separate Genres with commas',
            'not_found' => 'No Genres found',
            'parent_item_colon' => 'Parent Genre:',
        ]);
    });

    it('keeps the labels that the entity already declares', function (): void {
        $captured = null;
        Functions\expect('register_taxonomy')->once()->andReturnUsing(function ($slug, $type, $args) use (&$captured) {
            $captured = $args;

            return new stdClass;
        });

        (new WordPressTaxonomyRegistry)->register(contentTaxonomyEntity('genre', ['labels' => ['name' => 'Shelves']]));

        expect($captured['labels'])->toBe(['name' => 'Shelves']);
    });

    it('refuses an entity without a slug and does not call WordPress', function (): void {
        Functions\expect('register_taxonomy')->never();

        expect((new WordPressTaxonomyRegistry)->register(contentTaxonomyEntity('')))->toBeFalse()
            ->and((new WordPressTaxonomyRegistry)->register(new stdClass))->toBeFalse();
    });

    it('reports a failure when WordPress answers with an error', function (): void {
        Functions\expect('register_taxonomy')->once()->andReturn(new WP_Error('invalid', 'nope'));

        expect((new WordPressTaxonomyRegistry)->register(contentTaxonomyEntity('genre')))->toBeFalse();
    });

    it('asks WordPress whether a taxonomy exists', function (): void {
        Functions\expect('taxonomy_exists')->twice()->andReturnUsing(fn (string $slug): bool => $slug === 'genre');

        $registry = new WordPressTaxonomyRegistry;

        expect($registry->exists('genre'))->toBeTrue()
            ->and($registry->exists('movie'))->toBeFalse();
    });

    it('lists only the taxonomies that are not built in', function (): void {
        $custom = ['genre' => new stdClass];
        Functions\expect('get_taxonomies')->once()->with(['_builtin' => false], 'objects')->andReturn($custom);

        expect((new WordPressTaxonomyRegistry)->getAll())->toBe($custom);
    });
});
