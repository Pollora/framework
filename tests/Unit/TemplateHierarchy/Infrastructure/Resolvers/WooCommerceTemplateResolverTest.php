<?php

declare(strict_types=1);

use Mockery as m;
use Pollora\TemplateHierarchy\Infrastructure\Resolvers\WooCommerceTemplateResolver;

beforeEach(function () {
    // Initialize WordPress mocks
    setupWordPressMocks();

    // Add default mock for get_post_meta
    WP::$wpFunctions->shouldReceive('get_post_meta')
        ->byDefault()
        ->andReturn('');
});

afterEach(function () {
    m::close();
});

/**
 * Helper method to define a mock WooCommerce function
 */
function defineMockWooCommerceFunction(string $function, $returnValue): void
{
    if (! function_exists($function)) {
        eval("function $function() { return \$GLOBALS['$function']; }");
    }

    $GLOBALS[$function] = $returnValue;
}

/**
 * Helper method to define a mock WordPress function
 */
function defineMockWpFunction(string $function, $returnValue): void
{
    if (! function_exists($function)) {
        eval("function $function() { return \$GLOBALS['$function']; }");
    }

    $GLOBALS[$function] = $returnValue;
}

test('it checks product category condition', function () {
    defineMockWooCommerceFunction('is_product_category', true);

    $resolver = new WooCommerceTemplateResolver('product_category');
    expect($resolver->applies())->toBeTrue();

    // Test when condition is not satisfied
    defineMockWooCommerceFunction('is_product_category', false);
    expect($resolver->applies())->toBeFalse();
});

test('it checks product tag condition', function () {
    defineMockWooCommerceFunction('is_product_tag', true);

    $resolver = new WooCommerceTemplateResolver('product_tag');
    expect($resolver->applies())->toBeTrue();

    // Test when condition is not satisfied
    defineMockWooCommerceFunction('is_product_tag', false);
    expect($resolver->applies())->toBeFalse();
});

test('it checks product taxonomy condition', function () {
    defineMockWooCommerceFunction('is_product_taxonomy', true);
    defineMockWooCommerceFunction('is_product_category', false);
    defineMockWooCommerceFunction('is_product_tag', false);

    $resolver = new WooCommerceTemplateResolver('product_taxonomy');
    expect($resolver->applies())->toBeTrue();

    // Test when product_category is true (should be false)
    defineMockWooCommerceFunction('is_product_taxonomy', true);
    defineMockWooCommerceFunction('is_product_category', true);
    defineMockWooCommerceFunction('is_product_tag', false);

    expect($resolver->applies())->toBeFalse();
});

test('it checks shop condition', function () {
    defineMockWooCommerceFunction('is_shop', true);

    $resolver = new WooCommerceTemplateResolver('shop');
    expect($resolver->applies())->toBeTrue();

    // Test when condition is not satisfied
    defineMockWooCommerceFunction('is_shop', false);
    expect($resolver->applies())->toBeFalse();
});

test('it checks product condition', function () {
    defineMockWooCommerceFunction('is_product', true);

    $resolver = new WooCommerceTemplateResolver('product');
    expect($resolver->applies())->toBeTrue();

    // Test when condition is not satisfied
    defineMockWooCommerceFunction('is_product', false);
    expect($resolver->applies())->toBeFalse();
});

test('it returns correct origin', function () {
    $resolver = new WooCommerceTemplateResolver('product');

    // Make origin property accessible for testing
    $reflector = new \ReflectionObject($resolver);
    $property = $reflector->getProperty('origin');
    $property->setAccessible(true);

    expect($property->getValue($resolver))->toBe('woocommerce');
});

test('it generates candidates for cart', function () {
    $resolver = new WooCommerceTemplateResolver('cart');

    // Mock applies method to always return true for this test
    $resolverMock = m::mock($resolver)->makePartial();
    $resolverMock->shouldReceive('applies')->andReturn(true);

    $candidates = $resolverMock->getCandidates();

    // We should have candidates for PHP and Blade
    expect($candidates)->toHaveCount(2)
        ->and($candidates[0]->type)->toBe('php')
        ->and($candidates[0]->templatePath)->toBe('woocommerce/cart.php')
        ->and($candidates[0]->origin)->toBe('woocommerce')
        ->and($candidates[1]->type)->toBe('blade')
        ->and($candidates[1]->templatePath)->toBe('woocommerce.cart');
});

test('it generates product templates', function () {
    // Mock a WP_Post object
    $product = (object) [
        'ID' => 123,
        'post_name' => 'test-product',
    ];

    // Configure WordPress mock functions
    setupWordPressMocks();

    // Set up WordPress mocks for get_queried_object
    WP::$wpFunctions->shouldReceive('get_queried_object')
        ->andReturn($product);

    // Mock get_post_meta to return a custom template
    WP::$wpFunctions->shouldReceive('get_post_meta')
        ->with($product->ID, '_wp_page_template', true)
        ->andReturn('custom-template.php');

    // Ensure is_product returns true
    defineMockWooCommerceFunction('is_product', true);

    // Mock wc_get_product to return an object with get_type method
    $productType = new class
    {
        public function get_type()
        {
            return 'simple';
        }
    };

    defineMockWooCommerceFunction('wc_get_product', $productType);

    $resolver = new WooCommerceTemplateResolver('product');

    // Test applies() method directly to ensure it works
    expect($resolver->applies())->toBeTrue();

    // Get the candidates
    $candidates = $resolver->getCandidates();

    // Check that all expected templates are in the candidates
    $templatePaths = array_map(function ($candidate) {
        return $candidate->templatePath;
    }, $candidates);

    // Vérifier que les templates attendus sont présents
    $hasCustomTemplate = false;
    $hasProductNameTemplate = false;
    $hasProductTypeTemplate = false;
    $hasProductTemplate = false;

    foreach ($templatePaths as $path) {
        if ($path === 'custom-template.php') {
            $hasCustomTemplate = true;
        }
        if ($path === 'woocommerce/single-product-test-product.php') {
            $hasProductNameTemplate = true;
        }
        if ($path === 'woocommerce/single-product-simple.php') {
            $hasProductTypeTemplate = true;
        }
        if ($path === 'woocommerce/single-product.php') {
            $hasProductTemplate = true;
        }
    }

    expect($hasCustomTemplate)->toBeTrue('custom-template.php should be in templates');
    expect($hasProductNameTemplate)->toBeTrue('single-product-test-product.php should be in templates');
    expect($hasProductTypeTemplate)->toBeTrue('single-product-simple.php should be in templates');
    expect($hasProductTemplate)->toBeTrue('single-product.php should be in templates');
});
