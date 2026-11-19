<?php

declare(strict_types=1);

use Pollora\BlockPattern\Domain\Models\PatternCategory;

describe('PatternCategory', function (): void {
    it('exposes its slug and attributes', function (): void {
        $category = new PatternCategory('featured', ['label' => 'Featured']);

        expect($category->getSlug())->toBe('featured')
            ->and($category->getAttributes())->toBe(['label' => 'Featured']);
    });

    it('has no attributes unless it is given some', function (): void {
        expect((new PatternCategory('featured'))->getAttributes())->toBe([]);
    });

    it('describes itself for registration', function (): void {
        expect((new PatternCategory('featured', ['label' => 'Featured']))->toArray())->toBe([
            'slug' => 'featured',
            'attributes' => ['label' => 'Featured'],
        ]);
    });
});
