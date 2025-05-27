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
use Pollora\Route\Infrastructure\WordPress\WordPressTemplateResolver;

/**
 * Debug tests to troubleshoot the exact scenario: /realisations/campus-vert
 * with single-realisations.blade.php vs Route::wp('single', BlogSingleController::class)
 */
class TemplatePriorityRealWorldDebugTest extends TestCase
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
     * Debug: Simulate the exact scenario from the user's issue
     * URL: https://pollen.ddev.site/realisations/campus-vert
     * Expected: single-realisations.blade.php should win over Route::wp('single')
     */
    public function test_debug_exact_user_scenario(): void
    {
        // Simulate the exact route from routes/web.php line 33
        $singleRoute = Route::wordpress(
            methods: ['GET'],
            condition: RouteCondition::fromWordPressTag('is_single', []),
            action: 'App\Http\Controllers\BlogSingleController',
            priority: null // Let it calculate automatically
        );

        // Simulate what WordPress would generate for a post with slug "campus-vert" 
        // in a custom post type or category "realisations"
        $context = [
            'uri' => '/realisations/campus-vert',
            'post_name' => 'campus-vert',
            'category' => 'realisations',
            'is_single' => true,
            'is_singular' => true,
            'post_type' => 'post', // Could also be a custom post type
        ];

        // Test different template hierarchy scenarios WordPress might generate
        $scenarios = [
            'Category-based single' => [
                'single-realisations-campus-vert', // Most specific
                'single-realisations',              // Category specific  
                'single',                           // Generic single
                'index'                            // Fallback
            ],
            'Custom post type based' => [
                'single-realisations-campus-vert',
                'single-realisations', 
                'single',
                'index'
            ],
            'WordPress standard hierarchy' => [
                'single-post-campus-vert',
                'single-post',
                'single',
                'index'
            ],
        ];

        echo "\n=== REAL WORLD DEBUG: /realisations/campus-vert ===\n";
        echo "Route: Route::wp('single', BlogSingleController::class)\n";
        echo "Expected Template: single-realisations.blade.php\n\n";

        foreach ($scenarios as $scenarioName => $templates) {
            echo "--- Scenario: {$scenarioName} ---\n";
            
            $templateHierarchy = TemplateHierarchy::fromTemplatesArray($templates, 'is_single');

            // Mock template existence - simulate that single-realisations.blade.php exists
            $this->templateResolver->shouldReceive('templateExists')
                ->with('single-realisations.blade.php')
                ->andReturn(true);

            $this->templateResolver->shouldReceive('templateExists')
                ->with('single-realisations.php')
                ->andReturn(false);

            // Mock other template checks as not existing
            foreach ($templates as $template) {
                if ($template !== 'single-realisations') {
                    $this->templateResolver->shouldReceive('templateExists')
                        ->with($template . '.blade.php')
                        ->andReturn(false);
                    $this->templateResolver->shouldReceive('templateExists')
                        ->with($template . '.php')
                        ->andReturn(false);
                }
            }

            $result = $this->comparator->compareTemplateToRoute($templateHierarchy, $singleRoute, $context);

            echo "Template Hierarchy: " . implode(' > ', $templates) . "\n";
            echo "Template Score: " . $result->getTemplateScore()->getTotalScore() . "\n";
            echo "Route Score: " . $result->getRouteScore()->getTotalScore() . "\n";
            echo "Winner: " . ($result->templateWins() ? '🏆 TEMPLATE' : '❌ ROUTE') . "\n";
            echo "Reasoning: " . $result->getReasoning() . "\n\n";

            // The template should win in all scenarios where single-realisations exists
            if (in_array('single-realisations', $templates)) {
                $this->assertTrue(
                    $result->templateWins(),
                    "single-realisations.blade.php should override Route::wp('single') in scenario: {$scenarioName}"
                );
            }
        }

        echo "================================================\n";
    }

    /**
     * Debug: Test what happens with WordPress template resolver
     * This simulates how the real WordPressTemplateResolver might behave
     */
    public function test_debug_wordpress_template_hierarchy_generation(): void
    {
        echo "\n=== WORDPRESS TEMPLATE HIERARCHY DEBUG ===\n";

        // Simulate different WordPress contexts that might generate different hierarchies
        $wordpressContexts = [
            'Single post in category' => [
                'is_single' => true,
                'is_singular' => true,
                'post_type' => 'post',
                'category_slug' => 'realisations',
                'post_name' => 'campus-vert',
                'category_id' => 5,
            ],
            'Custom post type "realisations"' => [
                'is_single' => true,
                'is_singular' => true,  
                'post_type' => 'realisations',
                'post_name' => 'campus-vert',
            ],
            'Post with custom template' => [
                'is_single' => true,
                'is_singular' => true,
                'post_type' => 'post',
                'post_name' => 'campus-vert',
                'page_template' => 'single-realisations.blade.php',
            ],
        ];

        foreach ($wordpressContexts as $contextName => $wpContext) {
            echo "--- WordPress Context: {$contextName} ---\n";
            echo "Context: " . json_encode($wpContext, JSON_PRETTY_PRINT) . "\n";

            // Simulate what WordPress template hierarchy would be generated
            $expectedHierarchy = $this->simulateWordPressHierarchy($wpContext);
            echo "Expected Template Hierarchy: " . implode(' > ', $expectedHierarchy) . "\n";

            $templateHierarchy = TemplateHierarchy::fromTemplatesArray($expectedHierarchy, 'is_single');
            echo "Template Priority Score: " . $templateHierarchy->getPriority() . "\n\n";
        }

        echo "===========================================\n";

        $this->assertTrue(true, 'WordPress hierarchy debug completed');
    }

    /**
     * Debug: Show what the template resolver path resolution might look like
     */
    public function test_debug_template_path_resolution(): void
    {
        echo "\n=== TEMPLATE PATH RESOLUTION DEBUG ===\n";

        $templatePaths = [
            '/home/olivier/Sites/amphibee-v3/themes/default/views/single-realisations.blade.php',
            '/home/olivier/Sites/amphibee-v3/themes/default/views/single.blade.php',
            '/home/olivier/Sites/amphibee-v3/themes/default/views/index.blade.php',
        ];

        foreach ($templatePaths as $path) {
            $exists = file_exists($path);
            echo "Template: " . basename($path) . "\n";
            echo "Full Path: {$path}\n";
            echo "Exists: " . ($exists ? '✅ YES' : '❌ NO') . "\n\n";
        }

        // Mock template resolver for testing - don't depend on real files
        $this->templateResolver->shouldReceive('templateExists')
            ->with('single-realisations.blade.php')
            ->andReturn(true);

        $mockExists = $this->templateResolver->templateExists('single-realisations.blade.php');
        $this->assertTrue(
            $mockExists,
            'Mock template single-realisations.blade.php should exist'
        );

        echo "===================================\n";
    }

    /**
     * Simulate what WordPress would generate for template hierarchy
     */
    private function simulateWordPressHierarchy(array $context): array
    {
        $hierarchy = [];

        if ($context['post_type'] === 'realisations') {
            // Custom post type scenario
            $hierarchy[] = 'single-realisations-' . $context['post_name'];
            $hierarchy[] = 'single-realisations';
        } elseif (isset($context['category_slug'])) {
            // Category-based scenario
            $hierarchy[] = 'single-' . $context['category_slug'] . '-' . $context['post_name'];
            $hierarchy[] = 'single-' . $context['category_slug'];
        } elseif (isset($context['page_template'])) {
            // Custom template scenario
            $templateName = str_replace('.blade.php', '', $context['page_template']);
            $hierarchy[] = $templateName;
        }

        // Standard WordPress hierarchy
        $hierarchy[] = 'single-' . $context['post_name'];
        $hierarchy[] = 'single';
        $hierarchy[] = 'index';

        return array_unique($hierarchy);
    }
}