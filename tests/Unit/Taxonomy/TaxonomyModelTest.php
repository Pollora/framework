<?php

declare(strict_types=1);

use Pollora\Taxonomy\Domain\Models\Taxonomy;

describe('Taxonomy model', function (): void {
    it('exposes what it was built with', function (): void {
        $taxonomy = new Taxonomy('genre', ['book', 'movie'], ['name' => 'Genres'], ['hierarchical' => true]);

        expect($taxonomy->getSlug())->toBe('genre')
            ->and($taxonomy->getObjectTypes())->toBe(['book', 'movie'])
            ->and($taxonomy->getLabels())->toBe(['name' => 'Genres'])
            ->and($taxonomy->getArgs())->toBe(['hierarchical' => true]);
    });

    it('accepts a single object type and defaults to no labels or arguments', function (): void {
        $taxonomy = new Taxonomy('genre', 'book');

        expect($taxonomy->getObjectTypes())->toBe('book')
            ->and($taxonomy->getLabels())->toBe([])
            ->and($taxonomy->getArgs())->toBe([]);
    });
});
