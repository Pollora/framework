<?php

declare(strict_types=1);

use Pollora\PostType\Domain\Models\PostType;

describe('PostType model', function (): void {
    it('exposes what it was built with', function (): void {
        $postType = new PostType('book', 'Book', 'Books', ['public' => true]);

        expect($postType->getSlug())->toBe('book')
            ->and($postType->getSingularName())->toBe('Book')
            ->and($postType->getPluralName())->toBe('Books')
            ->and($postType->getArgs())->toBe(['public' => true]);
    });

    it('leaves the names and the arguments empty by default', function (): void {
        $postType = new PostType('book');

        expect($postType->getSingularName())->toBeNull()
            ->and($postType->getPluralName())->toBeNull()
            ->and($postType->getArgs())->toBe([]);
    });

    it('returns a new post type when arguments are added, the new ones winning', function (): void {
        $original = new PostType('book', 'Book', 'Books', ['public' => true, 'menu_position' => 5]);

        $changed = $original->withArgs(['menu_position' => 9, 'has_archive' => true]);

        expect($changed)->not->toBe($original)
            ->and($changed->getArgs())->toBe(['public' => true, 'menu_position' => 9, 'has_archive' => true])
            ->and($changed->getSlug())->toBe('book')
            ->and($changed->getSingularName())->toBe('Book')
            ->and($changed->getPluralName())->toBe('Books')
            ->and($original->getArgs())->toBe(['public' => true, 'menu_position' => 5]);
    });
});
