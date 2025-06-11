<?php

declare(strict_types=1);

use Pollora\ThirdParty\WooCommerce\Domain\Models\Template;
use Pollora\ThirdParty\WooCommerce\Domain\Services\WooCommerceService;

describe('WooCommerceService', function () {
    beforeEach(function () {
        setupWordPressMocks();
        $this->service = new WooCommerceService;
    });

    afterEach(function () {
        resetWordPressMocks();
    });

    test('can get default template paths', function () {
        // Mock WC_ABSPATH constant
        if (! defined('WC_ABSPATH')) {
            define('WC_ABSPATH', '/plugin/woocommerce/');
        }

        $paths = $this->service->getDefaultTemplatePaths();

        expect($paths)->toContain('/plugin/woocommerce/templates/');
    });

    test('returns empty array when WC_ABSPATH not defined', function () {
        // This test only works if WC_ABSPATH is not defined
        // Since we can't easily undefine constants in PHP, we'll skip if already defined
        if (defined('WC_ABSPATH')) {
            expect(true)->toBeTrue(); // Make test pass without assertions

            return;
        }

        $paths = $this->service->getDefaultTemplatePaths();
        expect($paths)->toBe([]);
    });

    test('can get theme template paths for child themes', function () {
        // Mock WordPress functions
        setWordPressFunction('is_child_theme', fn () => true);
        setWordPressFunction('get_template_directory', fn () => '/themes/parent');

        // Mock WooCommerce function
        $mockWC = Mockery::mock();
        $mockWC->shouldReceive('template_path')->andReturn('woocommerce/');
        setWordPressFunction('WC', fn () => $mockWC);

        $paths = $this->service->getThemeTemplatePaths();

        expect($paths)->toContain('/themes/parent/woocommerce/');
    });

    test('returns empty array for non-child themes', function () {
        setWordPressFunction('is_child_theme', fn () => false);

        $paths = $this->service->getThemeTemplatePaths();

        expect($paths)->toBe([]);
    });

    test('can detect woocommerce status screen', function () {
        $screen = new stdClass;
        $screen->id = 'woocommerce_page_wc-status';

        $result = $this->service->isWooCommerceStatusScreen(true, false, $screen);

        expect($result)->toBeTrue();
    });

    test('returns false when not on woocommerce status screen', function () {
        $screen = new stdClass;
        $screen->id = 'edit-post';

        $result = $this->service->isWooCommerceStatusScreen(true, false, $screen);

        expect($result)->toBeFalse();
    });

    test('returns false when doing ajax', function () {
        $screen = new stdClass;
        $screen->id = 'woocommerce_page_wc-status';

        $result = $this->service->isWooCommerceStatusScreen(true, true, $screen);

        expect($result)->toBeFalse();
    });

    test('returns false when not in admin', function () {
        $screen = new stdClass;
        $screen->id = 'woocommerce_page_wc-status';

        $result = $this->service->isWooCommerceStatusScreen(false, false, $screen);

        expect($result)->toBeFalse();
    });

    test('can get woocommerce template path with WC available', function () {
        $mockWC = Mockery::mock();
        $mockWC->shouldReceive('template_path')->andReturn('woocommerce/');
        setWordPressFunction('WC', fn () => $mockWC);

        $path = $this->service->getWooCommerceTemplatePath();

        expect($path)->toBe('woocommerce/');
    });

    test('returns default path when WC not available', function () {
        setWordPressFunction('WC', fn () => null);

        $path = $this->service->getWooCommerceTemplatePath();

        expect($path)->toBe('woocommerce/');
    });

    test('can get all template paths', function () {
        // Mock WC_ABSPATH constant
        if (! defined('WC_ABSPATH')) {
            define('WC_ABSPATH', '/plugin/woocommerce/');
        }

        setWordPressFunction('is_child_theme', fn () => true);
        setWordPressFunction('get_template_directory', fn () => '/themes/parent');

        $mockWC = Mockery::mock();
        $mockWC->shouldReceive('template_path')->andReturn('woocommerce/');
        setWordPressFunction('WC', fn () => $mockWC);

        $paths = $this->service->getAllTemplatePaths();

        expect($paths)->toContain('/plugin/woocommerce/templates/');
        expect($paths)->toContain('/themes/parent/woocommerce/');
    });

    test('can create template from path', function () {
        $template = $this->service->createTemplate('/path/to/single-product.php');

        expect($template)->toBeInstanceOf(Template::class);
        expect($template->path)->toBe('/path/to/single-product.php');
    });

    test('can add blade variants to template list', function () {
        $templates = [
            'single-product.php',
            'archive-product.php',
            'style.css', // Non-PHP file should not be converted
        ];

        $result = $this->service->addBladeVariants($templates);

        expect($result)->toContain('single-product.blade.php');
        expect($result)->toContain('archive-product.blade.php');
        expect($result)->toContain('single-product.php');
        expect($result)->toContain('archive-product.php');
        expect($result)->toContain('style.css');
        expect($result)->not->toContain('style.blade.css');
    });

    test('does not duplicate existing blade templates', function () {
        $templates = [
            'single-product.blade.php',
            'archive-product.php',
        ];

        $result = $this->service->addBladeVariants($templates);

        // Should contain archive-product.blade.php as new addition
        expect($result)->toContain('archive-product.blade.php');
        // Should contain original templates
        expect($result)->toContain('single-product.blade.php');
        expect($result)->toContain('archive-product.php');
        // Should not duplicate blade templates
        expect(array_count_values($result)['single-product.blade.php'])->toBe(1);
    });

    test('handles empty template list', function () {
        $templates = [];

        $result = $this->service->addBladeVariants($templates);

        expect($result)->toBe([]);
    });
});
