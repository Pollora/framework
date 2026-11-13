<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Pollora\Support\AbstractRecursiveIterator;
use Pollora\Support\RecursiveMenuIterator;
use Tests\Unit\Support\Fixtures\FakeMenuItem;

use function Tests\Unit\Support\Fixtures\support_wordpress;

require_once __DIR__.'/support-fixtures.php';

/**
 * @param  array<int, FakeMenuItem>  $items
 * @return array<int, string>
 */
function pollora_walk_menu(RecursiveMenuIterator $menu): array
{
    $titles = [];
    $walker = new RecursiveIteratorIterator($menu, RecursiveIteratorIterator::SELF_FIRST);

    foreach ($walker as $item) {
        $titles[] = str_repeat('-', $walker->getDepth()).$item->title;
    }

    return $titles;
}

describe('RecursiveMenuIterator over a collection', function (): void {
    beforeEach(function (): void {
        $this->grandChild = new FakeMenuItem(3, 2, 'Team');
        $this->grandChild->children = collect();

        $this->child = new FakeMenuItem(2, 1, 'About');
        $this->child->children = collect([$this->grandChild]);

        $this->parent = new FakeMenuItem(1, 0, 'Home');
        $this->parent->children = collect([$this->child]);

        $this->other = new FakeMenuItem(4, 0, 'Contact');
        $this->other->children = collect();

        $this->menu = new RecursiveMenuIterator(collect([$this->parent, $this->other]));
    });

    it('walks every item with its depth, children right after their parent', function (): void {
        expect(pollora_walk_menu($this->menu))->toBe(['Home', '-About', '--Team', 'Contact']);
    });

    it('tells which items have children', function (): void {
        $this->menu->rewind();

        expect($this->menu->hasChildren())->toBeTrue();

        $this->menu->next();

        expect($this->menu->hasChildren())->toBeFalse();
    });

    it('returns the children of the current item as an iterator of their own', function (): void {
        $this->menu->rewind();

        $children = $this->menu->getChildren();

        expect($children)->toBeInstanceOf(RecursiveMenuIterator::class)
            ->and($children->current())->toBe($this->child)
            ->and($children->hasChildren())->toBeTrue();
    });

    it('exposes the current item, its key and its validity', function (): void {
        $this->menu->rewind();

        expect($this->menu->valid())->toBeTrue()
            ->and($this->menu->key())->toBe(0)
            ->and($this->menu->current())->toBe($this->parent);

        $this->menu->next();

        expect($this->menu->key())->toBe(1)
            ->and($this->menu->current())->toBe($this->other);

        $this->menu->next();

        expect($this->menu->valid())->toBeFalse();
    });

    it('starts over on rewind', function (): void {
        $this->menu->next();
        $this->menu->next();
        $this->menu->rewind();

        expect($this->menu->key())->toBe(0)
            ->and($this->menu->current())->toBe($this->parent);
    });
});

describe('RecursiveMenuIterator from a menu location', function (): void {
    it('builds the tree of the menu assigned to the location', function (): void {
        $items = [
            new FakeMenuItem(10, 0, 'Home'),
            new FakeMenuItem(11, 0, 'Shop'),
            new FakeMenuItem(12, 11, 'Shoes'),
            new FakeMenuItem(13, 12, 'Boots'),
        ];
        $menuObject = (object) ['term_id' => 5];

        support_wordpress([
            'get_nav_menu_locations' => fn (): array => ['primary' => 5],
            'wp_get_nav_menu_object' => fn (int $id): ?object => $id === 5 ? $menuObject : null,
            'wp_get_nav_menu_items' => fn (object $menu): array => $menu === $menuObject ? $items : [],
        ]);

        $menu = new RecursiveMenuIterator('primary');

        expect(pollora_walk_menu($menu))->toBe(['Home', 'Shop', '-Shoes', '--Boots']);
    });

    it('keeps the menu order at every level of the tree', function (): void {
        $items = [
            new FakeMenuItem(1, 0, 'A'),
            new FakeMenuItem(2, 1, 'B'),
            new FakeMenuItem(5, 2, 'B1'),
            new FakeMenuItem(6, 2, 'B2'),
            new FakeMenuItem(3, 1, 'C'),
            new FakeMenuItem(4, 0, 'D'),
        ];
        $menuObject = (object) ['term_id' => 5];

        support_wordpress([
            'get_nav_menu_locations' => fn (): array => ['primary' => 5],
            'wp_get_nav_menu_object' => fn (int $id): ?object => $id === 5 ? $menuObject : null,
            'wp_get_nav_menu_items' => fn (object $menu): array => $menu === $menuObject ? $items : [],
        ]);

        $menu = new RecursiveMenuIterator('primary');

        expect(pollora_walk_menu($menu))->toBe(['A', '-B', '--B1', '--B2', '-C', 'D']);
    });

    it('is empty when no menu is assigned to the location', function (): void {
        support_wordpress(['get_nav_menu_locations' => fn (): array => ['footer' => 9]]);

        $menu = new RecursiveMenuIterator('primary');
        $menu->rewind();

        expect($menu->valid())->toBeFalse();
    });
});

describe('RecursiveMenuIterator without a usable menu', function (): void {
    it('is empty when given neither a collection nor a known menu', function (): void {
        $menu = new RecursiveMenuIterator(new Collection);
        $menu->rewind();

        expect($menu->valid())->toBeFalse();
    });
});

describe('AbstractRecursiveIterator', function (): void {
    it('iterates the items it is given, as a collection or as an array', function (Collection|array $items): void {
        $iterator = new class($items) extends AbstractRecursiveIterator
        {
            public function hasChildren(): bool
            {
                return false;
            }

            public function getChildren(): ?AbstractRecursiveIterator
            {
                return null;
            }
        };

        expect(iterator_to_array($iterator))->toBe(['a', 'b', 'c']);
    })->with([
        'collection' => [fn (): Collection => collect(['a', 'b', 'c'])],
        'array' => [['a', 'b', 'c']],
    ]);
});
