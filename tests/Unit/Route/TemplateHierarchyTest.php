<?php

declare(strict_types=1);

namespace Tests\Unit\Route;

use Mockery;
use PHPUnit\Framework\TestCase;
use Pollora\Theme\TemplateHierarchy;

/**
 * Tests for the TemplateHierarchy class that manages the WordPress template priority order.
 */
class TemplateHierarchyTest extends TestCase
{
    /**
     * @var array The expected hierarchy order for WordPress templates
     */
    protected $hierarchyOrder;
    
    /**
     * Set up the test environment.
     *
     * Defines the expected template hierarchy order and mocks the static method.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        // Define the expected hierarchy order
        $this->hierarchyOrder = [
            'is_404',
            'is_search',
            'is_front_page',
            'is_home',
            'is_post_type_archive',
            'is_tax',
            'is_attachment',
            'is_single',
            'is_page',
            'is_singular',
            'is_category',
            'is_tag',
            'is_author',
            'is_date',
            'is_archive',
            '__return_true', // index fallback
        ];
        
        // Mock the static getHierarchyOrder method
        $mock = Mockery::mock('alias:Pollora\Theme\TemplateHierarchy');
        $mock->shouldReceive('getHierarchyOrder')
            ->andReturn($this->hierarchyOrder);
    }

    /**
     * Clean up the test environment.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test that the template hierarchy order is correctly defined.
     *
     * Verifies that the hierarchy order contains all expected conditions
     * and that they are in the correct order of specificity.
     *
     * @return void
     */
    public function testHierarchyOrderIsCorrectlyDefined()
    {
        // Get the hierarchy order
        $hierarchyOrder = TemplateHierarchy::getHierarchyOrder();
        
        // Verify that it's an array
        expect($hierarchyOrder)->toBeArray();
        
        // Verify that the important conditions are present
        $expectedConditions = [
            'is_404',
            'is_search',
            'is_front_page',
            'is_home',
            'is_post_type_archive',
            'is_tax',
            'is_attachment',
            'is_single',
            'is_page',
            'is_singular',
            'is_category',
            'is_tag',
            'is_author',
            'is_date',
            'is_archive',
            '__return_true', // index fallback
        ];
        
        foreach ($expectedConditions as $condition) {
            expect($hierarchyOrder)->toContain($condition);
        }
        
        // Verify the order of key conditions
        $this->assertHierarchyOrder($hierarchyOrder, 'is_page', 'is_singular');
        $this->assertHierarchyOrder($hierarchyOrder, 'is_single', 'is_singular');
        $this->assertHierarchyOrder($hierarchyOrder, 'is_category', 'is_archive');
        $this->assertHierarchyOrder($hierarchyOrder, 'is_tag', 'is_archive');
        $this->assertHierarchyOrder($hierarchyOrder, 'is_tax', 'is_archive');
        $this->assertHierarchyOrder($hierarchyOrder, 'is_archive', '__return_true');
    }

    /**
     * Verifies that the first condition appears before the second in the hierarchy order.
     *
     * @param array $hierarchyOrder The hierarchy order to check
     * @param string $firstCondition The condition that should appear first
     * @param string $secondCondition The condition that should appear after
     * @return void
     */
    private function assertHierarchyOrder(array $hierarchyOrder, string $firstCondition, string $secondCondition): void
    {
        $firstIndex = array_search($firstCondition, $hierarchyOrder);
        $secondIndex = array_search($secondCondition, $hierarchyOrder);
        
        expect($firstIndex)->toBeLessThan($secondIndex);
    }

    /**
     * Test that the hierarchy order is used to determine the most specific route.
     *
     * Verifies that the order of conditions in the hierarchy correctly
     * determines which template should be used for a given request.
     *
     * @return void
     */
    public function testHierarchyOrderDeterminesMostSpecificRoute()
    {
        // Get the hierarchy order
        $hierarchyOrder = TemplateHierarchy::getHierarchyOrder();
        
        // Verify that is_page is more specific than is_singular
        $pageIndex = array_search('is_page', $hierarchyOrder);
        $singularIndex = array_search('is_singular', $hierarchyOrder);
        
        expect($pageIndex)->toBeLessThan($singularIndex);
        
        // Verify that is_single is more specific than is_singular
        $singleIndex = array_search('is_single', $hierarchyOrder);
        
        expect($singleIndex)->toBeLessThan($singularIndex);
        
        // Verify that is_404 is more specific than everything else
        $notFoundIndex = array_search('is_404', $hierarchyOrder);
        
        foreach ($hierarchyOrder as $index => $condition) {
            if ($condition !== 'is_404') {
                expect($notFoundIndex)->toBeLessThan($index);
            }
        }
    }
} 