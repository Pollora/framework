<?php

use Illuminate\Support\Str;
use Pollora\PostType\AbstractPostType;

/**
 * Create a test post type class that extends AbstractPostType
 */
function createTestPostType()
{
    return new class extends AbstractPostType
    {
        public function getName(): string
        {
            return parent::getName();
        }

        public function getPluralName(): string
        {
            return parent::getPluralName();
        }

        public function getLabels(): array
        {
            return [];
        }
    };
}

test('slug is generated from class name', function () {
    $postType = createTestPostType();

    $className = class_basename($postType);
    $expectedSlug = Str::kebab($className);

    expect($postType->getSlug())->toBe($expectedSlug);
});

test('name is generated from class name', function () {
    $postType = createTestPostType();

    $className = class_basename($postType);
    $snakeCase = Str::snake($className);
    $expectedName = ucfirst(str_replace('_', ' ', $snakeCase));
    $expectedName = Str::singular($expectedName);

    expect($postType->getName())->toBe($expectedName);
});

test('plural name is generated from class name', function () {
    $postType = createTestPostType();

    $className = class_basename($postType);
    $snakeCase = Str::snake($className);
    $humanized = ucfirst(str_replace('_', ' ', $snakeCase));
    $expectedPluralName = Str::plural($humanized);

    expect($postType->getPluralName())->toBe($expectedPluralName);
});

test('complex class names are properly humanized', function () {
    // Test with different class names
    $testCases = [
        'ProductCategory' => [
            'slug' => 'product-category',
            'name' => 'Product category',
            'plural' => 'Product categories',
        ],
        'BlogPost' => [
            'slug' => 'blog-post',
            'name' => 'Blog post',
            'plural' => 'Blog posts',
        ],
        'FAQ' => [
            'slug' => 'f-a-q',
            'name' => 'F a q',
            'plural' => 'F a qs',
        ],
        'TeamMember' => [
            'slug' => 'team-member',
            'name' => 'Team member',
            'plural' => 'Team members',
        ],
    ];

    foreach ($testCases as $className => $expected) {
        // Test slug generation
        expect(Str::kebab($className))->toBe($expected['slug']);

        // Test name generation
        $snakeCase = Str::snake($className);
        $humanized = ucfirst(str_replace('_', ' ', $snakeCase));
        $singularName = Str::singular($humanized);
        expect($singularName)->toBe($expected['name']);

        // Test plural name generation
        $pluralName = Str::plural($singularName);
        expect($pluralName)->toBe($expected['plural']);
    }
});
