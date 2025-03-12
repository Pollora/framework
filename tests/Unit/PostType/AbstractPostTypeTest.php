<?php

declare(strict_types=1);

namespace Tests\Unit\PostType;

use Illuminate\Support\Str;
use PHPUnit\Framework\TestCase;
use Pollora\PostType\AbstractPostType;

class AbstractPostTypeTest extends TestCase
{
    public function test_slug_is_generated_from_class_name(): void
    {
        $postType = new class extends AbstractPostType
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

        $className = class_basename($postType);
        $expectedSlug = Str::kebab($className);

        $this->assertEquals($expectedSlug, $postType->getSlug());
    }

    public function test_name_is_generated_from_class_name(): void
    {
        $postType = new class extends AbstractPostType
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

        $className = class_basename($postType);
        $snakeCase = Str::snake($className);
        $expectedName = ucfirst(str_replace('_', ' ', $snakeCase));
        $expectedName = Str::singular($expectedName);

        $this->assertEquals($expectedName, $postType->getName());
    }

    public function test_plural_name_is_generated_from_class_name(): void
    {
        $postType = new class extends AbstractPostType
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

        $className = class_basename($postType);
        $snakeCase = Str::snake($className);
        $humanized = ucfirst(str_replace('_', ' ', $snakeCase));
        $expectedPluralName = Str::plural($humanized);

        $this->assertEquals($expectedPluralName, $postType->getPluralName());
    }

    public function test_complex_class_name_is_properly_humanized(): void
    {
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
