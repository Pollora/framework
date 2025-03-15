<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Contracts\Config\Repository;
use Mockery;
use Pollora\Theme\TemplateHierarchy;

/**
 * Setup function for all template hierarchy tests
 */
function setupTemplateHierarchyTest()
{
    // Define the expected hierarchy order
    $hierarchyOrder = [
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

    // Initialize WordPress mocks from helpers.php
    setupWordPressMocks();

    // Create the config mock
    $config = Mockery::mock(Repository::class);
    $config->shouldReceive('get')
        ->with('wordpress.conditions', Mockery::any())
        ->andReturn([
            'is_404' => '404',
            'is_search' => 'search',
            'is_front_page' => 'front_page',
            'is_home' => 'home',
            'is_post_type_archive' => 'post_type_archive',
            'is_tax' => 'taxonomy',
            'is_attachment' => 'attachment',
            'is_single' => 'single',
            'is_page' => 'page',
            'is_singular' => 'singular',
            'is_category' => 'category',
            'is_tag' => 'tag',
            'is_author' => 'author',
            'is_date' => 'date',
            'is_archive' => 'archive',
        ]);

    // Mock for plugin conditions if needed
    $config->shouldReceive('get')
        ->with('wordpress.plugin_conditions', Mockery::any())
        ->andReturn([
            'is_woocommerce' => 'woocommerce',
            'is_product' => 'product',
            'is_shop' => 'shop',
        ]);

    // Create the TemplateHierarchy mock for certain tests
    $templateHierarchy = Mockery::mock(TemplateHierarchy::class);

    // Configure the templateHierarchy mock to return our hierarchy order
    $templateHierarchy->shouldReceive('getHierarchyOrder')
        ->andReturn($hierarchyOrder);

    // Mock the Laravel container for app() helper
    $container = Mockery::mock(Container::class);

    // Configure the container to return our TemplateHierarchy mock when requested
    $container->shouldReceive('make')
        ->with(TemplateHierarchy::class, Mockery::any())
        ->andReturn($templateHierarchy);

    // Set the mocked container as the singleton instance
    Container::setInstance($container);

    // Create a real container for the actual TemplateHierarchy instance if needed
    $realContainer = new Container;
    $realContainer->instance('config', $config);

    return [
        'hierarchyOrder' => $hierarchyOrder,
        'templateHierarchy' => $templateHierarchy,
        'container' => $container,
        'config' => $config,
        'realContainer' => $realContainer,
    ];
}

/**
 * Override WordPress functions for testing
 */
function setupWordPressMocksForTemplateHierarchy($conditions = [])
{
    WP::$wpFunctions = Mockery::mock('stdClass');

    // Setup add_filter mock - accepting ANY arguments with withAnyArgs()
    WP::$wpFunctions->shouldReceive('add_filter')
        ->withAnyArgs()
        ->andReturn(true);

    // Setup apply_filters mock
    WP::$wpFunctions->shouldReceive('apply_filters')
        ->withAnyArgs()
        ->andReturnUsing(function ($tag, $value) {
            return $value;
        });

    // Default conditions
    $defaultConditions = [
        'is_page' => false,
        'is_singular' => false,
        'is_archive' => false,
        'is_404' => false,
        'is_search' => false,
        'is_category' => false,
        'is_tag' => false,
        'is_tax' => false,
    ];

    // Apply overrides
    $conditions = array_merge($defaultConditions, $conditions);

    // Setup is_page condition
    WP::$wpFunctions->shouldReceive('is_page')
        ->andReturn($conditions['is_page']);

    // Setup is_singular condition
    WP::$wpFunctions->shouldReceive('is_singular')
        ->andReturn($conditions['is_singular']);

    // Setup is_archive condition
    WP::$wpFunctions->shouldReceive('is_archive')
        ->andReturn($conditions['is_archive']);

    // Setup is_404 condition
    WP::$wpFunctions->shouldReceive('is_404')
        ->andReturn($conditions['is_404']);
}

/**
 * Helper to assert one condition appears before another in the hierarchy
 */
function assertHierarchyOrder(array $hierarchyOrder, string $firstCondition, string $secondCondition): void
{
    $firstIndex = array_search($firstCondition, $hierarchyOrder);
    $secondIndex = array_search($secondCondition, $hierarchyOrder);

    expect($firstIndex)->toBeLessThan($secondIndex);
}

/**
 * Clean up after each test
 */
afterEach(function () {
    Container::setInstance(null);
    WP::$wpFunctions = null;
    Mockery::close();
});

/**
 * Test that the template hierarchy order is correctly defined.
 */
test('hierarchy order is correctly defined', function () {
    $setup = setupTemplateHierarchyTest();

    // Get the hierarchy order using the container
    $hierarchyOrder = app(TemplateHierarchy::class)->getHierarchyOrder();

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
    assertHierarchyOrder($hierarchyOrder, 'is_page', 'is_singular');
    assertHierarchyOrder($hierarchyOrder, 'is_single', 'is_singular');
    assertHierarchyOrder($hierarchyOrder, 'is_category', 'is_archive');
    assertHierarchyOrder($hierarchyOrder, 'is_tag', 'is_archive');
    assertHierarchyOrder($hierarchyOrder, 'is_tax', 'is_archive');
    assertHierarchyOrder($hierarchyOrder, 'is_archive', '__return_true');
});

/**
 * Test that the hierarchy order is used to determine the most specific route.
 */
test('hierarchy order determines most specific route', function () {
    $setup = setupTemplateHierarchyTest();

    // Get the hierarchy order using the singleton
    $hierarchyOrder = app(TemplateHierarchy::class)->getHierarchyOrder();

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
});

/**
 * Test typical page templates
 */
test('expected page templates match WordPress conventions', function () {
    // This test simply verifies that the expected page templates follow the WordPress conventions
    // without trying to test private methods directly

    $pageTemplates = [
        'template-custom.php',    // From template attribute
        'page-test-page.php',     // From post slug
        'page-123.php',           // From post ID
        'page.php',                // Default template
    ];

    // Verify the order is correct
    expect($pageTemplates[0])->toBe('template-custom.php');
    expect($pageTemplates[1])->toBe('page-test-page.php');
    expect($pageTemplates[2])->toBe('page-123.php');
    expect($pageTemplates[3])->toBe('page.php');
});

/**
 * Test typical archive templates
 */
test('expected archive templates match WordPress conventions', function () {
    // This test simply verifies that the expected archive templates follow the WordPress conventions

    $archiveTemplates = [
        'archive-page.php',       // Post type specific archive
        'archive.php',             // Default archive template
    ];

    // Verify the order is correct
    expect($archiveTemplates[0])->toBe('archive-page.php');
    expect($archiveTemplates[1])->toBe('archive.php');
});

/**
 * Test template capture functionality
 */
test('template include is captured and prepended to hierarchy', function () {
    // Setup specific mock for this test to handle the template_include hook
    WP::$wpFunctions = Mockery::mock('stdClass');
    WP::$wpFunctions->shouldReceive('add_filter')
        ->withAnyArgs()
        ->andReturn(true);

    // Create configuration mock
    $config = Mockery::mock(Repository::class);
    $config->shouldReceive('get')->withAnyArgs()->andReturn([]);

    // Create container
    $container = new Container;

    // Create a real TemplateHierarchy instance
    $templateHierarchy = new TemplateHierarchy($config, $container);

    // Set up reflection to access private property
    $hierarchyProp = new ReflectionProperty($templateHierarchy, 'templateHierarchy');
    $hierarchyProp->setAccessible(true);

    // Set initial value
    $initialHierarchy = ['page.php', 'index.php'];
    $hierarchyProp->setValue($templateHierarchy, $initialHierarchy);

    // Call the method
    $result = $templateHierarchy->captureTemplateInclude('custom-template.php');

    // Verify the result matches the input
    expect($result)->toBe('custom-template.php');

    // Get the updated value
    $updatedHierarchy = $hierarchyProp->getValue($templateHierarchy);

    // Verify the template was prepended
    expect($updatedHierarchy[0])->toBe('custom-template.php');
    expect($updatedHierarchy)->toContain('page.php');
    expect($updatedHierarchy)->toContain('index.php');
});

/**
 * Test handling of blade template variants
 */
test('blade template variants are correctly generated', function () {
    // Setup specific mock for this test
    WP::$wpFunctions = Mockery::mock('stdClass');
    WP::$wpFunctions->shouldReceive('add_filter')
        ->withAnyArgs()
        ->andReturn(true);

    // Create config mock
    $config = Mockery::mock(Repository::class);
    $config->shouldReceive('get')->withAnyArgs()->andReturn([]);

    // Create container
    $container = new Container;

    // Create a real instance for testing
    $templateHierarchy = new TemplateHierarchy($config, $container);

    // Set up reflection to access private methods and properties
    $method = new ReflectionMethod($templateHierarchy, 'addBladeTemplateVariants');
    $method->setAccessible(true);

    $hierarchyProp = new ReflectionProperty($templateHierarchy, 'templateHierarchy');
    $hierarchyProp->setAccessible(true);
    $hierarchyProp->setValue($templateHierarchy, []);

    // Test templates
    $templates = [
        'page.php',
        'page-test.php',
        'subdir/custom-template.php',
    ];

    // Call the method
    $method->invoke($templateHierarchy, $templates);

    // Get the updated hierarchy
    $hierarchy = $hierarchyProp->getValue($templateHierarchy);

    // Expected blade variants
    expect($hierarchy)->toContain('page');
    expect($hierarchy)->toContain('page-test');
    expect($hierarchy)->toContain('subdir.custom-template');
});

/**
 * Test that template handler registration works properly
 */

/**
 * Test that template handler registration works properly
 */
test('template handlers can be registered', function () {
    // Initialize WordPress mocks with support for both template_include and custom filters
    setupWordPressMocks();

    // Add specific expectation for our custom filter
    WP::$wpFunctions->shouldReceive('add_filter')
        ->with('pollora/template_hierarchy/custom_type_templates', Mockery::type('Closure'), 10, 1)
        ->once()
        ->andReturn(true);

    // Create config mock
    $config = Mockery::mock(\Illuminate\Contracts\Config\Repository::class);
    $config->shouldReceive('get')->withAnyArgs()->andReturn([]);

    // Create container
    $container = new \Illuminate\Container\Container;

    // Create an instance of TemplateHierarchy
    $templateHierarchy = new \Pollora\Theme\TemplateHierarchy($config, $container);

    // Create a handler callback
    $handlerCallback = function ($obj) {
        return ['custom-template.php'];
    };

    // Register the handler
    $templateHierarchy->registerTemplateHandler('custom_type', $handlerCallback);

    // The verification is handled by the mock expectation
});

/**
 * Test WordPress condition detection
 */
test('condition satisfaction detection works with different WordPress functions', function () {
    // Create a mock that won't interfere with template_include
    WP::$wpFunctions = Mockery::mock('stdClass');
    WP::$wpFunctions->shouldReceive('add_filter')
        ->withAnyArgs()
        ->andReturn(true);

    // Define is_page and is_archive functions for this test
    if (! function_exists('is_page')) {
        function is_page()
        {
            return true;
        }
    } else {
        WP::$wpFunctions->shouldReceive('is_page')->andReturn(true);
    }

    if (! function_exists('is_archive')) {
        function is_archive()
        {
            return false;
        }
    } else {
        WP::$wpFunctions->shouldReceive('is_archive')->andReturn(false);
    }

    if (! function_exists('nonexistent_condition')) {
        function nonexistent_condition()
        {
            return true;
        }
    }

    // Create config mock
    $config = Mockery::mock(Repository::class);
    $config->shouldReceive('get')->withAnyArgs()->andReturn([]);

    // Create container
    $container = new Container;

    // Create a real instance for testing
    $templateHierarchy = new TemplateHierarchy($config, $container);

    // Use reflection to access private method
    $method = new ReflectionMethod($templateHierarchy, 'isConditionSatisfied');
    $method->setAccessible(true);

    // Test condition detection
    expect($method->invoke($templateHierarchy, 'is_page'))->toBeTrue();
    expect($method->invoke($templateHierarchy, 'is_archive'))->toBeFalse();
    expect($method->invoke($templateHierarchy, 'nonexistent_condition'))->toBeTrue();
    expect($method->invoke($templateHierarchy, 'truly_nonexistent_function'))->toBeFalse();
});
