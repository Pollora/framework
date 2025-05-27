<?php

declare(strict_types=1);

namespace Tests\Unit\Route\Infrastructure\WordPress;

use PHPUnit\Framework\TestCase;
use Pollora\Route\Infrastructure\WordPress\ConditionalTagsResolver;

/**
 * @covers \Pollora\Route\Infrastructure\WordPress\ConditionalTagsResolver
 */
class ConditionalTagsResolverTest extends TestCase
{
    private ConditionalTagsResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Load the WordPress config structure for testing
        $config = [
            'conditions' => [
                'is_embed' => 'embed',
                'is_404' => '404',
                'is_search' => 'search',
                'is_paged' => 'paged',
                'is_front_page' => ['/', 'front'],
                'is_home' => ['home', 'blog'],
                'is_privacy_policy' => 'privacy_policy',
                'is_post_type_archive' => ['post-type-archive', 'postTypeArchive'],
                'is_tax' => 'taxonomy',
                'is_attachment' => 'attachment',
                'is_page_template' => 'template',
                'is_sticky' => 'sticky',
                'is_single' => 'single',
                'is_subpage' => ['subpage', 'subpageof'],
                'is_page' => 'page',
                'is_singular' => 'singular',
                'is_category' => ['category', 'cat'],
                'is_tag' => 'tag',
                'is_author' => 'author',
                'is_date' => 'date',
                'is_year' => 'year',
                'is_month' => 'month',
                'is_day' => 'day',
                'is_time' => 'time',
                'is_archive' => 'archive',
            ],
            'plugin_conditions' => [
                'woocommerce' => [
                    'is_shop' => 'shop',
                    'is_product' => 'product',
                    'is_cart' => 'cart',
                    'is_checkout' => 'checkout',
                    'is_account_page' => 'account',
                    'is_product_category' => 'product_category',
                    'is_product_tag' => 'product_tag',
                    'is_wc_endpoint_url' => 'wc_endpoint',
                ],
            ],
        ];
        
        $this->resolver = new ConditionalTagsResolver($config);
    }

    public function testResolveAliasWithString(): void
    {
        $this->assertEquals('is_page', $this->resolver->resolveAlias('page'));
        $this->assertEquals('is_category', $this->resolver->resolveAlias('category'));
        $this->assertEquals('is_category', $this->resolver->resolveAlias('cat'));
        $this->assertEquals('is_single', $this->resolver->resolveAlias('single'));
    }

    public function testResolveAliasWithArray(): void
    {
        $this->assertEquals('is_front_page', $this->resolver->resolveAlias('/'));
        $this->assertEquals('is_front_page', $this->resolver->resolveAlias('front'));
        $this->assertEquals('is_home', $this->resolver->resolveAlias('home'));
        $this->assertEquals('is_home', $this->resolver->resolveAlias('blog'));
    }

    public function testResolveAliasWithDirectWordPressFunction(): void
    {
        // When passing a direct WordPress function, it should return as-is
        $this->assertEquals('is_page', $this->resolver->resolveAlias('is_page'));
        $this->assertEquals('is_single', $this->resolver->resolveAlias('is_single'));
        $this->assertEquals('is_category', $this->resolver->resolveAlias('is_category'));
    }

    public function testResolveAliasWithUnknown(): void
    {
        // Unknown aliases should return as-is
        $this->assertEquals('unknown_condition', $this->resolver->resolveAlias('unknown_condition'));
        $this->assertEquals('custom_func', $this->resolver->resolveAlias('custom_func'));
    }

    public function testHasConditionWithDirectFunction(): void
    {
        // Mock WordPress functions
        if (!function_exists('is_page')) {
            $this->markTestSkipped('WordPress functions not available in unit tests');
        }
        
        $this->assertTrue($this->resolver->hasCondition('is_page'));
        $this->assertTrue($this->resolver->hasCondition('is_single'));
    }

    public function testHasConditionWithAlias(): void
    {
        // Should resolve alias before checking
        $this->assertTrue($this->resolver->hasCondition('page'));
        $this->assertTrue($this->resolver->hasCondition('single'));
        $this->assertTrue($this->resolver->hasCondition('category'));
        $this->assertTrue($this->resolver->hasCondition('cat'));
    }

    public function testGetAvailableConditions(): void
    {
        $conditions = $this->resolver->getAvailableConditions();
        
        // Should include both WordPress functions and aliases
        $this->assertContains('is_page', $conditions);
        $this->assertContains('page', $conditions);
        $this->assertContains('category', $conditions);
        $this->assertContains('cat', $conditions);
    }

    public function testRegisterCustomCondition(): void
    {
        $this->resolver->registerCondition('is_vip', fn() => true);
        
        $this->assertTrue($this->resolver->hasCondition('is_vip'));
        $this->assertTrue($this->resolver->resolve('is_vip'));
    }

    public function testValidateParametersForKnownFunction(): void
    {
        // Test parameter validation for known WordPress functions
        $this->assertTrue($this->resolver->validateParameters('is_page', []));
        $this->assertTrue($this->resolver->validateParameters('is_page', ['about']));
        $this->assertTrue($this->resolver->validateParameters('is_page', [123]));
        $this->assertTrue($this->resolver->validateParameters('is_page', [['about', 'contact']]));
    }

    public function testValidateParametersForFunctionWithoutParams(): void
    {
        // Functions that don't accept parameters
        $this->assertTrue($this->resolver->validateParameters('is_404', []));
        $this->assertTrue($this->resolver->validateParameters('is_home', []));
        $this->assertTrue($this->resolver->validateParameters('is_search', []));
    }

    public function testPluginConditionsAreLoaded(): void
    {
        // Plugin conditions should be available if the plugin is active
        $this->assertTrue($this->resolver->hasCondition('is_shop'));
        $this->assertTrue($this->resolver->hasCondition('shop'));
        $this->assertEquals('is_shop', $this->resolver->resolveAlias('shop'));
    }

    public function testCaseInsensitiveAliasResolution(): void
    {
        // Aliases should be case-insensitive when possible
        $this->assertEquals('is_category', $this->resolver->resolveAlias('category'));
        $this->assertEquals('is_category', $this->resolver->resolveAlias('CATEGORY'));
    }

    public function testComplexAliasConfiguration(): void
    {
        // Test with complex alias configuration
        $this->resolver->registerAliases([
            'is_custom_type' => ['custom', 'my_type', 'special'],
        ]);
        
        $this->assertEquals('is_custom_type', $this->resolver->resolveAlias('custom'));
        $this->assertEquals('is_custom_type', $this->resolver->resolveAlias('my_type'));
        $this->assertEquals('is_custom_type', $this->resolver->resolveAlias('special'));
    }

    public function testRegressionTestForExistingConditions(): void
    {
        // Test that all existing WordPress conditions work as before
        $wpConditions = [
            'is_page', 'is_single', 'is_category', 'is_tag', 'is_archive',
            'is_home', 'is_front_page', 'is_404', 'is_search'
        ];
        
        foreach ($wpConditions as $condition) {
            $this->assertTrue($this->resolver->hasCondition($condition), 
                "WordPress condition {$condition} should be available");
            $this->assertEquals($condition, $this->resolver->resolveAlias($condition),
                "WordPress condition {$condition} should resolve to itself");
        }
    }
}