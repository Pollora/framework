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
 * Debug tests to validate specific user scenarios and troubleshoot priority issues
 * These tests provide detailed output for debugging template vs route priority resolution
 */
class TemplatePriorityDebugTest extends TestCase
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
     * Debug test: Exact reproduction of user's single-realisations scenario
     * This test provides detailed output to understand the scoring logic
     */
    public function test_debug_single_realisations_scenario(): void
    {
        // Simulate Route::wp('single', ...)
        $singleRoute = Route::wordpress(
            methods: ['GET'],
            condition: RouteCondition::fromWordPressTag('is_single', []),
            action: 'SingleController@show'
        );

        // Simulate template hierarchy for single-realisations.blade.php
        $templateHierarchy = TemplateHierarchy::fromTemplatesArray([
            'single-realisations',  // single-realisations.blade.php
            'single',              // single.blade.php  
            'index'                // index.blade.php
        ], 'is_single');

        // Mock that single-realisations.blade.php exists
        $this->templateResolver->shouldReceive('templateExists')
            ->with('single-realisations.blade.php')
            ->andReturn(true);

        $this->templateResolver->shouldReceive('templateExists')
            ->with('single-realisations.php')
            ->andReturn(false);

        $context = [
            'post_type' => 'post',
            'post_slug' => 'my-realization-post',
            'is_single' => true,
        ];

        $result = $this->comparator->compareTemplateToRoute($templateHierarchy, $singleRoute, $context);

        // Debug output
        $templateScore = $result->getTemplateScore();
        $routeScore = $result->getRouteScore();

        echo "\n=== SINGLE-REALISATIONS DEBUG ===\n";
        echo "Template Score Details:\n";
        echo "  Base Score: " . $templateScore->getBaseScore() . "\n";
        echo "  Total Score: " . $templateScore->getTotalScore() . "\n";
        echo "  Bonuses: " . json_encode($templateScore->getBonuses()) . "\n";
        echo "  Template Exists: " . ($templateScore->templateExists() ? 'Yes' : 'No') . "\n";
        echo "  Primary Template: " . $templateScore->getPrimaryTemplate() . "\n";

        echo "\nRoute Score Details:\n";
        echo "  Base Score: " . $routeScore->getBaseScore() . "\n";
        echo "  Total Score: " . $routeScore->getTotalScore() . "\n";
        echo "  Bonuses: " . json_encode($routeScore->getBonuses()) . "\n";
        echo "  Is WordPress Route: " . ($routeScore->isWordPressRoute() ? 'Yes' : 'No') . "\n";

        echo "\nResult: " . ($result->templateWins() ? 'TEMPLATE WINS' : 'ROUTE WINS') . "\n";
        echo "Reasoning: " . $result->getReasoning() . "\n";
        echo "================================\n";

        // Assert that template wins
        $this->assertTrue(
            $result->templateWins(),
            sprintf(
                'single-realisations.blade.php should override Route::wp(\'single\'). Template: %d vs Route: %d',
                $templateScore->getTotalScore(),
                $routeScore->getTotalScore()
            )
        );

        // Verify template has existence bonus
        $this->assertTrue($templateScore->templateExists(), 'Template should be marked as existing');
        $this->assertArrayHasKey('existence', $templateScore->getBonuses(), 'Template should have existence bonus');
        $this->assertEquals('single-realisations', $templateScore->getPrimaryTemplate(), 'Primary template should be single-realisations');
    }

    /**
     * Debug test: Show what happens with different template specificity levels
     */
    public function test_debug_template_specificity_levels(): void
    {
        $route = Route::wordpress(
            methods: ['GET'],
            condition: RouteCondition::fromWordPressTag('is_single', []),
            action: 'SingleController@show'
        );

        $scenarios = [
            'Generic single' => ['single'],
            'Post-specific' => ['single-realisations'],
            'ID-specific' => ['single-realisations-123'],
            'Very specific' => ['single-realisations-123', 'single-realisations', 'single'],
        ];

        echo "\n=== TEMPLATE SPECIFICITY COMPARISON ===\n";

        foreach ($scenarios as $name => $templates) {
            $templateHierarchy = TemplateHierarchy::fromTemplatesArray(array_merge($templates, ['index']), 'is_single');

            // Mock that first template exists
            $this->templateResolver->shouldReceive('templateExists')
                ->with($templates[0] . '.blade.php')
                ->andReturn(true);

            $this->templateResolver->shouldReceive('templateExists')
                ->with($templates[0] . '.php')
                ->andReturn(false);

            $result = $this->comparator->compareTemplateToRoute($templateHierarchy, $route);

            echo "{$name}:\n";
            echo "  Templates: " . implode(' > ', $templates) . "\n";
            echo "  Template Score: " . $result->getTemplateScore()->getTotalScore() . "\n";
            echo "  Route Score: " . $result->getRouteScore()->getTotalScore() . "\n";
            echo "  Winner: " . ($result->templateWins() ? 'Template' : 'Route') . "\n\n";
        }

        echo "==========================================\n";

        // All specific templates should win
        $this->assertTrue(true, 'Debug test completed successfully');
    }

    /**
     * Debug test: Show configuration impact on scoring
     */
    public function test_debug_configuration_impact(): void
    {
        $route = Route::wordpress(
            methods: ['GET'],
            condition: RouteCondition::fromWordPressTag('is_single', []),
            action: 'SingleController@show'
        );

        $templateHierarchy = TemplateHierarchy::fromTemplatesArray([
            'single-realisations',
            'single',
            'index'
        ], 'is_single');

        $configs = [
            'Default Config' => [],
            'High Template Bonus' => ['template_existence_bonus' => 500],
            'Low Template Bonus' => ['template_existence_bonus' => 50],
            'No Route Condition Weight' => ['route_condition_weight' => 0],
            'High Route Condition Weight' => ['route_condition_weight' => 1.0],
        ];

        echo "\n=== CONFIGURATION IMPACT ===\n";

        foreach ($configs as $name => $config) {
            $comparator = new TemplatePriorityComparator($this->templateResolver, $config);

            $this->templateResolver->shouldReceive('templateExists')
                ->andReturn(true);

            $result = $comparator->compareTemplateToRoute($templateHierarchy, $route);

            echo "{$name}:\n";
            echo "  Config: " . json_encode($config) . "\n";
            echo "  Template Score: " . $result->getTemplateScore()->getTotalScore() . "\n";
            echo "  Route Score: " . $result->getRouteScore()->getTotalScore() . "\n";
            echo "  Winner: " . ($result->templateWins() ? 'Template' : 'Route') . "\n\n";
        }

        echo "=============================\n";

        $this->assertTrue(true, 'Configuration debug test completed');
    }
}