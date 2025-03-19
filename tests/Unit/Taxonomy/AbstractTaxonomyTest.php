<?php

declare(strict_types=1);

namespace Tests\Unit\Taxonomy;

use Illuminate\Support\Str;
use PHPUnit\Framework\TestCase;
use Pollora\Taxonomy\AbstractTaxonomy;

class AbstractTaxonomyTest extends TestCase
{
    public function testSlugIsGeneratedFromClassName(): void
    {
        $taxonomy = new class extends AbstractTaxonomy {
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

        $className = class_basename($taxonomy);
        $expectedSlug = Str::kebab($className);

        $this->assertEquals($expectedSlug, $taxonomy->getSlug());
    }

    public function testNameIsGeneratedFromClassName(): void
    {
        $taxonomy = new class extends AbstractTaxonomy {
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

        $className = class_basename($taxonomy);
        $snakeCase = Str::snake($className);
        $expectedName = ucfirst(str_replace('_', ' ', $snakeCase));
        $expectedName = Str::singular($expectedName);

        $this->assertEquals($expectedName, $taxonomy->getName());
    }

    public function testPluralNameIsGeneratedFromClassName(): void
    {
        $taxonomy = new class extends AbstractTaxonomy {
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

        $className = class_basename($taxonomy);
        $snakeCase = Str::snake($className);
        $humanized = ucfirst(str_replace('_', ' ', $snakeCase));
        $expectedPluralName = Str::plural($humanized);

        $this->assertEquals($expectedPluralName, $taxonomy->getPluralName());
    }

    public function testComplexClassNameIsProperlyHumanized(): void
    {
        $taxonomy = new class extends AbstractTaxonomy {
            protected ?string $slug = null;

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

        // Test with different class names
        $testCases = [
            'ProductCategory' => [
                'slug' => 'product-category',
                'name' => 'Product category',
                'plural' => 'Product categories',
            ],
            'BlogTag' => [
                'slug' => 'blog-tag',
                'name' => 'Blog tag',
                'plural' => 'Blog tags',
            ],
            'EventType' => [
                'slug' => 'event-type',
                'name' => 'Event type',
                'plural' => 'Event types',
            ],
            'DocumentFormat' => [
                'slug' => 'document-format',
                'name' => 'Document format',
                'plural' => 'Document formats',
            ],
        ];

        foreach ($testCases as $className => $expected) {
            // Test slug generation
            $this->assertEquals($expected['slug'], Str::kebab($className));

            // Test name generation
            $snakeCase = Str::snake($className);
            $humanized = ucfirst(str_replace('_', ' ', $snakeCase));
            $singularName = Str::singular($humanized);
            $this->assertEquals($expected['name'], $singularName);

            // Test plural name generation
            $pluralName = Str::plural($singularName);
            $this->assertEquals($expected['plural'], $pluralName);
        }
    }
}
