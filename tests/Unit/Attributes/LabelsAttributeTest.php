<?php

declare(strict_types=1);

use Pollora\Attributes\PostType;
use Pollora\Attributes\PostType\Labels as PostTypeLabels;
use Pollora\Attributes\Taxonomy;
use Pollora\Attributes\Taxonomy\Labels as TaxonomyLabels;
use Pollora\PostType\Infrastructure\Services\PostTypeConfiguration;
use Pollora\Taxonomy\Infrastructure\Services\TaxonomyConfiguration;

// --- Test fixtures ---

#[PostType('project')]
#[PostTypeLabels(
    allItems: 'All Projects',
    addNew: 'Add Project',
    editItem: 'Edit Project',
)]
class ProjectWithLabels {}

#[PostType('article')]
class ArticleWithoutLabels {}

#[Taxonomy('genre', objectType: 'book')]
#[TaxonomyLabels(
    allItems: 'All Genres',
    editItem: 'Edit Genre',
    searchItems: 'Search Genres',
)]
class GenreWithLabels {}

// --- PostType Labels tests ---

describe('PostType Labels attribute', function (): void {
    it('accepts named parameters and filters null values', function (): void {
        $reflection = new ReflectionClass(ProjectWithLabels::class);
        $attrs = $reflection->getAttributes(PostTypeLabels::class);

        expect($attrs)->toHaveCount(1);

        $labels = $attrs[0]->newInstance();
        expect($labels)->toBeInstanceOf(PostTypeLabels::class);
    });

    it('merges with existing labels instead of replacing', function (): void {
        $config = new PostTypeConfiguration('project', 'Project', 'Projects', [
            'labels' => [
                'name' => 'Projects',
                'singular_name' => 'Project',
                'all_items' => 'All Projects (auto)',
                'edit_item' => 'Edit Project (auto)',
                'add_new' => 'Add New (auto)',
            ],
        ]);

        $reflection = new ReflectionClass(ProjectWithLabels::class);
        $attr = $reflection->getAttributes(PostTypeLabels::class)[0]->newInstance();
        $attr->handle(null, $config, $reflection, $attr);

        $labels = $config->attributeArgs['labels'];

        // Overridden by #[Labels]
        expect($labels['all_items'])->toBe('All Projects');
        expect($labels['add_new'])->toBe('Add Project');
        expect($labels['edit_item'])->toBe('Edit Project');

        // Preserved from auto-generated (not overridden)
        expect($labels['name'])->toBe('Projects');
        expect($labels['singular_name'])->toBe('Project');
    });

    it('does not set labels that were not provided', function (): void {
        $config = new PostTypeConfiguration('article', 'Article', 'Articles');

        $reflection = new ReflectionClass(ArticleWithoutLabels::class);
        $postTypeAttrs = $reflection->getAttributes(PostTypeLabels::class);

        expect($postTypeAttrs)->toHaveCount(0);
    });

    it('handles all named parameters correctly', function (): void {
        $labels = new PostTypeLabels(
            name: 'My Items',
            singularName: 'My Item',
            addNew: 'Create',
            notFound: 'Nothing here',
        );

        $config = new PostTypeConfiguration('item', 'Item', 'Items');
        $reflection = new ReflectionClass(ArticleWithoutLabels::class);
        $labels->handle(null, $config, $reflection, $labels);

        $result = $config->attributeArgs['labels'];

        expect($result)->toBe([
            'name' => 'My Items',
            'singular_name' => 'My Item',
            'add_new' => 'Create',
            'not_found' => 'Nothing here',
        ]);
    });
});

// --- Taxonomy Labels tests ---

describe('Taxonomy Labels attribute', function (): void {
    it('accepts named parameters', function (): void {
        $reflection = new ReflectionClass(GenreWithLabels::class);
        $attrs = $reflection->getAttributes(TaxonomyLabels::class);

        expect($attrs)->toHaveCount(1);
    });

    it('merges with existing taxonomy labels', function (): void {
        $config = new TaxonomyConfiguration('genre', 'Genre', 'Genres', 'book', [
            'labels' => [
                'name' => 'Genres',
                'singular_name' => 'Genre',
                'all_items' => 'All Genres (auto)',
                'edit_item' => 'Edit Genre (auto)',
                'search_items' => 'Search Genres (auto)',
                'not_found' => 'No Genres found (auto)',
            ],
        ]);

        $reflection = new ReflectionClass(GenreWithLabels::class);
        $attr = $reflection->getAttributes(TaxonomyLabels::class)[0]->newInstance();
        $attr->handle(null, $config, $reflection, $attr);

        $labels = $config->attributeArgs['labels'];

        // Overridden
        expect($labels['all_items'])->toBe('All Genres');
        expect($labels['edit_item'])->toBe('Edit Genre');
        expect($labels['search_items'])->toBe('Search Genres');

        // Preserved
        expect($labels['name'])->toBe('Genres');
        expect($labels['not_found'])->toBe('No Genres found (auto)');
    });
});

// --- textDomain attribute parameter ---

describe('textDomain attribute parameter', function (): void {
    it('is available on PostType attribute', function (): void {
        $attr = new PostType('product', textDomain: 'my-plugin');

        expect($attr->textDomain)->toBe('my-plugin');
    });

    it('defaults to null on PostType attribute', function (): void {
        $attr = new PostType('product');

        expect($attr->textDomain)->toBeNull();
    });

    it('is available on Taxonomy attribute', function (): void {
        $attr = new Taxonomy('category', textDomain: 'my-plugin');

        expect($attr->textDomain)->toBe('my-plugin');
    });

    it('defaults to null on Taxonomy attribute', function (): void {
        $attr = new Taxonomy('category');

        expect($attr->textDomain)->toBeNull();
    });
});
