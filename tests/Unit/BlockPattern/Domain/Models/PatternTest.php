<?php

declare(strict_types=1);

use Pollora\BlockPattern\Domain\Models\Pattern;

describe('Pattern', function (): void {
    it('creates pattern with required fields', function (): void {
        $pattern = new Pattern('my/pattern', 'My Pattern', '<!-- wp:paragraph --><p>Hello</p><!-- /wp:paragraph -->');

        expect($pattern->getSlug())->toBe('my/pattern');
        expect($pattern->getTitle())->toBe('My Pattern');
        expect($pattern->getContent())->toContain('Hello');
    });

    it('creates pattern with all optional fields', function (): void {
        $pattern = new Pattern(
            slug: 'ns/hero',
            title: 'Hero Section',
            content: '<div>Hero</div>',
            description: 'A hero banner',
            categories: ['featured', 'headers'],
            keywords: ['hero', 'banner'],
            blockTypes: ['core/cover'],
            postTypes: ['page', 'post'],
            inserter: true,
            viewportWidth: 1200
        );

        expect($pattern->getDescription())->toBe('A hero banner');
        expect($pattern->getCategories())->toBe(['featured', 'headers']);
        expect($pattern->getKeywords())->toBe(['hero', 'banner']);
        expect($pattern->getBlockTypes())->toBe(['core/cover']);
        expect($pattern->getPostTypes())->toBe(['page', 'post']);
        expect($pattern->getInserter())->toBeTrue();
        expect($pattern->getViewportWidth())->toBe(1200);
    });

    it('returns null for unset optional fields', function (): void {
        $pattern = new Pattern('slug', 'title', 'content');

        expect($pattern->getDescription())->toBeNull();
        expect($pattern->getCategories())->toBeNull();
        expect($pattern->getKeywords())->toBeNull();
        expect($pattern->getBlockTypes())->toBeNull();
        expect($pattern->getPostTypes())->toBeNull();
        expect($pattern->getInserter())->toBeNull();
        expect($pattern->getViewportWidth())->toBeNull();
    });

    it('converts to array with only set fields', function (): void {
        $pattern = new Pattern(
            slug: 'test/pattern',
            title: 'Test',
            content: '<p>Test</p>',
            categories: ['layout']
        );

        $array = $pattern->toArray();

        expect($array)->toHaveKeys(['slug', 'title', 'content', 'categories']);
        expect($array)->not->toHaveKey('description');
        expect($array)->not->toHaveKey('keywords');
        expect($array)->not->toHaveKey('blockTypes');
        expect($array['slug'])->toBe('test/pattern');
        expect($array['categories'])->toBe(['layout']);
    });

    it('converts to array with all fields when set', function (): void {
        $pattern = new Pattern(
            slug: 'full',
            title: 'Full',
            content: 'c',
            description: 'd',
            categories: ['cat'],
            keywords: ['kw'],
            blockTypes: ['bt'],
            postTypes: ['pt'],
            inserter: false,
            viewportWidth: 800
        );

        $array = $pattern->toArray();

        expect($array)->toHaveCount(10);
        expect($array['inserter'])->toBeFalse();
        expect($array['viewportWidth'])->toBe(800);
    });

    it('creates pattern from array', function (): void {
        $data = [
            'slug' => 'from/array',
            'title' => 'From Array',
            'description' => 'Created from array',
            'categories' => ['test'],
        ];

        $pattern = Pattern::fromArray($data, '<div>Content</div>');

        expect($pattern->getSlug())->toBe('from/array');
        expect($pattern->getTitle())->toBe('From Array');
        expect($pattern->getContent())->toBe('<div>Content</div>');
        expect($pattern->getDescription())->toBe('Created from array');
        expect($pattern->getCategories())->toBe(['test']);
    });
});
