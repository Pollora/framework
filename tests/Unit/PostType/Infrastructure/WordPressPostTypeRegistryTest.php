<?php

declare(strict_types=1);

use Brain\Monkey\Functions;
use Pollora\PostType\Infrastructure\Adapters\WordPressPostTypeRegistry;

function contentPostTypeEntity(string $slug, array $args = [], string $singular = 'Book', string $plural = 'Books'): object
{
    return new class($slug, $args, $singular, $plural)
    {
        public function __construct(
            private readonly string $slug,
            private readonly array $args,
            private readonly string $singular,
            private readonly string $plural,
        ) {}

        public function getSlug(): string
        {
            return $this->slug;
        }

        public function getArgs(): array
        {
            return $this->args;
        }

        public function getSingularName(): string
        {
            return $this->singular;
        }

        public function getPluralName(): string
        {
            return $this->plural;
        }
    };
}

describe('WordPressPostTypeRegistry', function (): void {
    it('hands the slug and the arguments to register_post_type', function (): void {
        $captured = null;
        Functions\expect('register_post_type')->once()->andReturnUsing(function ($slug, $args) use (&$captured) {
            $captured = [$slug, $args];

            return new stdClass;
        });

        $registered = (new WordPressPostTypeRegistry)->register(contentPostTypeEntity('book', ['public' => true]));

        expect($registered)->toBeTrue()
            ->and($captured[0])->toBe('book')
            ->and($captured[1]['public'])->toBeTrue();
    });

    it('generates the labels from the singular and plural names when none are given', function (): void {
        $captured = null;
        Functions\expect('register_post_type')->once()->andReturnUsing(function ($slug, $args) use (&$captured) {
            $captured = $args;

            return new stdClass;
        });

        (new WordPressPostTypeRegistry)->register(contentPostTypeEntity('book'));

        expect($captured['labels'])->toMatchArray([
            'name' => 'Books',
            'singular_name' => 'Book',
            'menu_name' => 'Books',
            'all_items' => 'All Books',
            'add_new_item' => 'Add New Book',
            'edit_item' => 'Edit Book',
            'search_items' => 'Search Books',
            'not_found' => 'No Books found',
            'not_found_in_trash' => 'No Books found in trash',
            'parent_item_colon' => 'Parent Book:',
        ]);
    });

    it('keeps the labels that the entity already declares', function (): void {
        $captured = null;
        Functions\expect('register_post_type')->once()->andReturnUsing(function ($slug, $args) use (&$captured) {
            $captured = $args;

            return new stdClass;
        });

        (new WordPressPostTypeRegistry)->register(contentPostTypeEntity('book', ['labels' => ['name' => 'Library']]));

        expect($captured['labels'])->toBe(['name' => 'Library']);
    });

    it('refuses an entity without a slug and does not call WordPress', function (): void {
        Functions\expect('register_post_type')->never();

        expect((new WordPressPostTypeRegistry)->register(contentPostTypeEntity('')))->toBeFalse()
            ->and((new WordPressPostTypeRegistry)->register(new stdClass))->toBeFalse();
    });

    it('reports a failure when WordPress answers with an error', function (): void {
        Functions\expect('register_post_type')->once()->andReturn(new WP_Error('invalid', 'nope'));

        expect((new WordPressPostTypeRegistry)->register(contentPostTypeEntity('book')))->toBeFalse();
    });

    it('asks WordPress whether a post type exists', function (): void {
        Functions\expect('post_type_exists')->twice()->andReturnUsing(fn (string $slug): bool => $slug === 'book');

        $registry = new WordPressPostTypeRegistry;

        expect($registry->exists('book'))->toBeTrue()
            ->and($registry->exists('movie'))->toBeFalse();
    });

    it('lists only the post types that are not built in', function (): void {
        $custom = ['book' => new stdClass];
        Functions\expect('get_post_types')->once()->with(['_builtin' => false], 'objects')->andReturn($custom);

        expect((new WordPressPostTypeRegistry)->getAll())->toBe($custom);
    });
});
