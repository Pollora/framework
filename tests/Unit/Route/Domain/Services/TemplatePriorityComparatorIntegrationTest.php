<?php

declare(strict_types=1);

namespace Tests\Unit\Route\Domain\Services;

use Tests\TestCase;
use Mockery;
use Pollora\Route\Domain\Contracts\TemplateResolverInterface;
use Pollora\Route\Domain\Models\Route;
use Pollora\Route\Domain\Models\RouteCondition;
use Pollora\Route\Domain\Models\TemplateHierarchy;
use Pollora\Route\Domain\Services\TemplatePriorityComparator;

/**
 * Integration tests for real-world template vs route priority scenarios
 * These tests validate concrete use cases where templates should override routes
 */
class TemplatePriorityComparatorIntegrationTest extends TestCase
{
    private TemplatePriorityComparator $comparator;
    private $templateResolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->templateResolver = Mockery::mock(TemplateResolverInterface::class);
        $this->comparator = new TemplatePriorityComparator($this->templateResolver);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test: single-realisations.blade.php should override Route::wp('single', ...)
     * This validates the specific issue reported by the user
     */
    public function test_single_realisations_template_overrides_generic_single_route(): void
    {
        // Create a generic single route - Route::wp('single', ...)
        $genericSingleRoute = $this->createWordPressRoute('is_single', [], 700); // Base single priority

        // Create template hierarchy for single-realisations post
        $templateHierarchy = $this->createSpecificSingleTemplateHierarchy('realisations');

        // Mock that the specific template exists
        $this->templateResolver->shouldReceive('templateExists')
            ->with('single-realisations.blade.php')
            ->andReturn(true);

        $this->templateResolver->shouldReceive('templateExists')
            ->with('single-realisations.php')
            ->andReturn(false);

        $context = [
            'post_type' => 'post',
            'post_slug' => 'realisations',
            'is_single' => true,
        ];

        $result = $this->comparator->compareTemplateToRoute($templateHierarchy, $genericSingleRoute, $context);

        // The specific template should win over the generic route
        $this->assertTrue(
            $result->templateWins(),
            sprintf(
                'single-realisations.blade.php should override generic single route. Template score: %d, Route score: %d. Reasoning: %s',
                $result->getTemplateScore()->getTotalScore(),
                $result->getRouteScore()->getTotalScore(),
                $result->getReasoning()
            )
        );

        // Verify the template has higher specificity
        $this->assertGreaterThan(
            $result->getRouteScore()->getTotalScore(),
            $result->getTemplateScore()->getTotalScore(),
            'Template should have higher total score than route'
        );
    }

    /**
     * Test: page-about.blade.php should override Route::wp('page', ...)
     */
    public function test_specific_page_template_overrides_generic_page_route(): void
    {
        // Generic page route
        $genericPageRoute = $this->createWordPressRoute('is_page', [], 800);

        // Specific page template hierarchy
        $templateHierarchy = $this->createSpecificPageTemplateHierarchy('about');

        $this->templateResolver->shouldReceive('templateExists')
            ->with('page-about.blade.php')
            ->andReturn(true);

        $this->templateResolver->shouldReceive('templateExists')
            ->with('page-about.php')
            ->andReturn(false);

        $context = [
            'post_type' => 'page',
            'post_slug' => 'about',
            'is_page' => true,
        ];

        $result = $this->comparator->compareTemplateToRoute($templateHierarchy, $genericPageRoute, $context);

        $this->assertTrue(
            $result->templateWins(),
            'page-about.blade.php should override generic page route'
        );
    }

    /**
     * Test: archive-products.blade.php should override Route::wp('archive', ...)
     */
    public function test_custom_post_type_archive_template_overrides_generic_archive_route(): void
    {
        // Generic archive route
        $genericArchiveRoute = $this->createWordPressRoute('is_archive', [], 400);

        // Custom post type archive template
        $templateHierarchy = $this->createCustomPostTypeArchiveHierarchy('products');

        $this->templateResolver->shouldReceive('templateExists')
            ->with('archive-products.blade.php')
            ->andReturn(true);

        $this->templateResolver->shouldReceive('templateExists')
            ->with('archive-products.php')
            ->andReturn(false);

        $context = [
            'post_type' => 'products',
            'is_archive' => true,
            'is_post_type_archive' => true,
        ];

        $result = $this->comparator->compareTemplateToRoute($templateHierarchy, $genericArchiveRoute, $context);

        $this->assertTrue(
            $result->templateWins(),
            'archive-products.blade.php should override generic archive route'
        );
    }

    /**
     * Test: Template with existence should still win over route, but verify we get proper reasoning
     */
    public function test_template_vs_route_scoring_behavior(): void
    {
        $genericSingleRoute = $this->createWordPressRoute('is_single', [], 700);
        $templateHierarchy = $this->createSpecificSingleTemplateHierarchy('non-existent');

        // Mock that template does NOT exist
        $this->templateResolver->shouldReceive('templateExists')
            ->andReturn(false);

        $result = $this->comparator->compareTemplateToRoute($templateHierarchy, $genericSingleRoute);

        // The new scoring system heavily favors templates, so verify we get proper reasoning
        $this->assertIsString($result->getReasoning(), 'Should provide detailed reasoning');
        $this->assertEquals(0, $result->getTemplateScore()->getTotalScore(), 'Template should have positive score');
        $this->assertGreaterThan(0, $result->getRouteScore()->getTotalScore(), 'Route should have positive score');

        // Debug output to understand the scoring
        $templateScore = $result->getTemplateScore()->getTotalScore();
        $routeScore = $result->getRouteScore()->getTotalScore();

        $this->assertTrue(
            $templateScore === 0 && $routeScore > 0,
            sprintf('Template: %d, Route: %d, Winner: %s',
                $templateScore,
                $routeScore,
                $result->templateWins() ? 'Template' : 'Route'
            )
        );
    }

    /**
     * Test: Laravel route should always win regardless of template specificity
     */
    public function test_laravel_route_always_wins_over_specific_templates(): void
    {
        $laravelRoute = $this->createLaravelRoute('/api/posts/{id}', ['GET']);
        $templateHierarchy = $this->createSpecificSingleTemplateHierarchy('api-post');

        $this->templateResolver->shouldReceive('templateExists')
            ->andReturn(true);

        $result = $this->comparator->compareTemplateToRoute($templateHierarchy, $laravelRoute);

        $this->assertFalse(
            $result->templateWins(),
            'Laravel routes should always win over WordPress templates'
        );
    }

    /**
     * Test: Very specific template (single-product-123.blade.php) should override less specific route
     */
    public function test_very_specific_template_overrides_route(): void
    {
        $productRoute = $this->createWordPressRoute('is_single', ['post_type' => 'product'], 750);

        // Very specific template with ID
        $templateHierarchy = TemplateHierarchy::fromTemplatesArray([
            'single-product-123',
            'single-product',
            'single',
            'index'
        ], 'is_single');

        $this->templateResolver->shouldReceive('templateExists')
            ->with('single-product-123.blade.php')
            ->andReturn(true);

        $this->templateResolver->shouldReceive('templateExists')
            ->with('single-product-123.php')
            ->andReturn(false);

        $context = [
            'post_type' => 'product',
            'post_id' => 123,
            'is_single' => true,
        ];

        $result = $this->comparator->compareTemplateToRoute($templateHierarchy, $productRoute, $context);

        $this->assertTrue(
            $result->templateWins(),
            'Very specific template (single-product-123.blade.php) should override route'
        );
    }

    /**
     * Test: Complex scenario with multiple context bonuses
     */
    public function test_complex_scenario_with_context_bonuses(): void
    {
        $adminRoute = $this->createWordPressRoute('is_admin', [], 500);
        $templateHierarchy = $this->createAdminTemplateHierarchy();

        $this->templateResolver->shouldReceive('templateExists')
            ->andReturn(true);

        $context = [
            'is_admin' => true,
            'post_type' => 'custom_product',
            'plugin_active' => 'woocommerce',
        ];

        $result = $this->comparator->compareTemplateToRoute($templateHierarchy, $adminRoute, $context);

        // Template should get bonuses for admin context, custom post type, and plugin context
        $this->assertGreaterThan(
            500, // Base admin route priority
            $result->getTemplateScore()->getTotalScore(),
            'Template should get context bonuses for admin, custom post type, and plugin'
        );
    }

    /**
     * Helper: Create a WordPress route with specific condition
     */
    private function createWordPressRoute(string $condition, array $parameters = [], int $priority = null): Route
    {
        $routeCondition = RouteCondition::fromWordPressTag($condition, $parameters);

        return Route::wordpress(
            methods: ['GET'],
            condition: $routeCondition,
            action: 'TestController@show',
            priority: $priority
        );
    }

    /**
     * Helper: Create a Laravel route
     */
    private function createLaravelRoute(string $uri, array $methods): Route
    {
        return Route::laravel(
            uri: $uri,
            methods: $methods,
            action: 'ApiController@show'
        );
    }

    /**
     * Helper: Create template hierarchy for specific single post (like single-realisations)
     */
    private function createSpecificSingleTemplateHierarchy(string $slug): TemplateHierarchy
    {
        return TemplateHierarchy::fromTemplatesArray([
            "single-{$slug}",    // Most specific: single-realisations.blade.php
            'single',            // Generic single.blade.php
            'index'              // Fallback index.blade.php
        ], 'is_single');
    }

    /**
     * Helper: Create template hierarchy for specific page (like page-about)
     */
    private function createSpecificPageTemplateHierarchy(string $slug): TemplateHierarchy
    {
        return TemplateHierarchy::fromTemplatesArray([
            "page-{$slug}",      // Most specific: page-about.blade.php
            'page',              // Generic page.blade.php
            'index'              // Fallback index.blade.php
        ], 'is_page');
    }

    /**
     * Helper: Create template hierarchy for custom post type archive
     */
    private function createCustomPostTypeArchiveHierarchy(string $postType): TemplateHierarchy
    {
        return TemplateHierarchy::fromTemplatesArray([
            "archive-{$postType}", // Most specific: archive-products.blade.php
            'archive',             // Generic archive.blade.php
            'index'                // Fallback index.blade.php
        ], 'is_archive');
    }

    /**
     * Helper: Create admin template hierarchy
     */
    private function createAdminTemplateHierarchy(): TemplateHierarchy
    {
        return TemplateHierarchy::fromTemplatesArray([
            'admin-dashboard',
            'admin',
            'index'
        ], 'is_admin');
    }
}
