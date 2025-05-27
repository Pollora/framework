<?php

declare(strict_types=1);

namespace Tests\Unit\Route\Infrastructure\WordPress;

use Tests\TestCase;
use Mockery;
use Pollora\Route\Domain\Contracts\ConditionResolverInterface;
use Pollora\Route\Domain\Services\WordPressContextBuilder;
use Pollora\Route\Infrastructure\WordPress\WordPressTemplateResolver;

/**
 * Test the enhanced single template hierarchy generation
 * Validates that category-specific templates like single-realisations.blade.php are properly detected
 */
class WordPressTemplateResolverTest extends TestCase
{
    private WordPressTemplateResolver $resolver;
    private $contextBuilder;
    private $conditionResolver;

    protected function setUp(): void
    {
        parent::setUp();

        setupWordPressMocks();

        $this->contextBuilder = Mockery::mock(WordPressContextBuilder::class);
        $this->conditionResolver = Mockery::mock(ConditionResolverInterface::class);

        // Setup default mock expectations for condition resolver
        $this->conditionResolver->shouldReceive('getAvailableConditions')
            ->andReturn(['is_page', 'is_single', 'is_category', 'is_archive', 'is_home'])
            ->byDefault();

        $this->conditionResolver->shouldReceive('resolve')
            ->withAnyArgs()
            ->andReturnUsing(function($condition, $params = []) {
                // Default behavior: return true for is_single, false for others
                return $condition === 'is_single';
            })
            ->byDefault();

        $this->resolver = new WordPressTemplateResolver(
            $this->contextBuilder,
            $this->conditionResolver
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        resetWordPressMocks();
        parent::tearDown();
    }

    /**
     * Test: Enhanced single hierarchy includes category-specific templates
     * This validates the fix for single-realisations.blade.php detection
     */
    public function test_enhanced_single_hierarchy_includes_category_templates(): void
    {
        // Mock a post in the "realisations" category
        $post = (object) [
            'ID' => 123,
            'post_name' => 'campus-vert',
            'post_type' => 'post',
        ];

        // Mock category
        $category = (object) [
            'slug' => 'realisations',
            'term_id' => 5,
            'name' => 'Réalisations',
        ];

        // Setup WordPress function mocks
        setWordPressFunction('is_singular', fn() => true);
        setWordPressFunction('is_page', fn() => false);
        setWordPressFunction('is_attachment', fn() => false);
        setWordPressFunction('get_the_category', fn($post_id) => [$category]);

        $this->contextBuilder->shouldReceive('extractPostFromContext')
            ->andReturn($post);

        $context = [
            'is_single' => true,
            'post' => $post,
        ];

        $hierarchy = $this->resolver->resolveHierarchy($context);
        $templates = $hierarchy->getTemplatesInOrder();

        // Verify category-specific templates are included
        $this->assertContains('single-realisations-campus-vert', $templates,
            'Should include most specific category template');
        $this->assertContains('single-realisations', $templates,
            'Should include category-specific template');
        $this->assertContains('single', $templates,
            'Should include generic single template');

        // Verify order - most specific first
        $firstTemplate = $templates[0];
        $this->assertEquals('single-realisations-campus-vert', $firstTemplate,
            'Most specific template should come first');

        // Find position of single-realisations in hierarchy
        $categoryTemplatePosition = array_search('single-realisations', $templates);
        $genericSinglePosition = array_search('single', $templates);

        $this->assertLessThan($genericSinglePosition, $categoryTemplatePosition,
            'Category template should come before generic single template');
    }

    /**
     * Test: Custom post type hierarchy
     */
    public function test_custom_post_type_hierarchy(): void
    {
        $post = (object) [
            'ID' => 456,
            'post_name' => 'my-project',
            'post_type' => 'projects',
        ];

        setWordPressFunction('is_singular', fn() => true);
        setWordPressFunction('is_page', fn() => false);
        setWordPressFunction('is_attachment', fn() => false);
        setWordPressFunction('get_the_category', fn($post_id) => []);

        $this->contextBuilder->shouldReceive('extractPostFromContext')
            ->andReturn($post);

        $hierarchy = $this->resolver->resolveHierarchy([]);
        $templates = $hierarchy->getTemplatesInOrder();

        $this->assertContains('single-projects-my-project', $templates);
        $this->assertContains('single-projects', $templates);
        $this->assertContains('single', $templates);
    }

    /**
     * Test: Post with multiple categories
     */
    public function test_post_with_multiple_categories(): void
    {
        $post = (object) [
            'ID' => 789,
            'post_name' => 'multi-cat-post',
            'post_type' => 'post',
        ];

        $categories = [
            (object) ['slug' => 'realisations'],
            (object) ['slug' => 'projets'],
        ];

        setWordPressFunction('is_singular', fn() => true);
        setWordPressFunction('is_page', fn() => false);
        setWordPressFunction('is_attachment', fn() => false);
        setWordPressFunction('get_the_category', fn($post_id) => $categories);

        $this->contextBuilder->shouldReceive('extractPostFromContext')
            ->andReturn($post);

        $hierarchy = $this->resolver->resolveHierarchy([]);
        $templates = $hierarchy->getTemplatesInOrder();

        // Should include templates for both categories
        $this->assertContains('single-realisations-multi-cat-post', $templates);
        $this->assertContains('single-realisations', $templates);
        $this->assertContains('single-projets-multi-cat-post', $templates);
        $this->assertContains('single-projets', $templates);
    }

    /**
     * Test: Template existence checking with Laravel view factory
     */
    public function test_template_exists_with_laravel_view_factory(): void
    {
        // Mock Laravel view factory
        $viewFactory = Mockery::mock('Illuminate\View\Factory');
        $viewFactory->shouldReceive('exists')
            ->with('single-realisations')
            ->andReturn(true);

        $viewFactory->shouldReceive('exists')
            ->with('single-realisations.blade')
            ->andReturn(false);

        // Configure the existing app container to return our mocked view factory
        $container = app();
        $container->instance('view', $viewFactory);

        $exists = $this->resolver->templateExists('single-realisations.blade.php');
        $this->assertTrue($exists, 'Template should be detected via Laravel view factory');
    }

    /**
     * Test: Real-world scenario debugging
     */
    public function test_real_world_campus_vert_scenario(): void
    {
        // Simulate the exact post from user's site
        $post = (object) [
            'ID' => 123,
            'post_name' => 'campus-vert',
            'post_type' => 'post',
        ];

        $category = (object) [
            'slug' => 'realisations',
            'term_id' => 5,
        ];

        setWordPressFunction('is_singular', fn() => true);
        setWordPressFunction('is_page', fn() => false);
        setWordPressFunction('is_attachment', fn() => false);
        setWordPressFunction('get_the_category', fn($post_id) => [$category]);

        $this->contextBuilder->shouldReceive('extractPostFromContext')
            ->andReturn($post);

        $hierarchy = $this->resolver->resolveHierarchy([
            'uri' => '/realisations/campus-vert',
            'is_single' => true,
        ]);

        $templates = $hierarchy->getTemplatesInOrder();

        $this->assertContains('single-realisations', $templates,
            'single-realisations template should be in hierarchy for campus-vert post');

        // Verify it comes before generic single
        $realisationsPos = array_search('single-realisations', $templates);
        $singlePos = array_search('single', $templates);
        $this->assertLessThan($singlePos, $realisationsPos,
            'single-realisations should have higher priority than generic single');
    }
}
