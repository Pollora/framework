<?php

declare(strict_types=1);

use Pollora\ThirdParty\WooCommerce\Domain\Models\Template;
use Pollora\ThirdParty\WooCommerce\Domain\Services\WooCommerceService;

describe('WooCommerceService', function (): void {
    beforeEach(function (): void {
        $this->service = new WooCommerceService;
    });

    test('can get default template paths', function (): void {
        if (! defined('WC_ABSPATH')) {
            define('WC_ABSPATH', '/plugin/woocommerce/');
        }

        $paths = $this->service->getDefaultTemplatePaths();

        expect($paths)->toContain('/plugin/woocommerce/templates/');
    });

    test('returns empty array when WC_ABSPATH not defined', function (): void {
        if (defined('WC_ABSPATH')) {
            expect(true)->toBeTrue();

            return;
        }

        $paths = $this->service->getDefaultTemplatePaths();
        expect($paths)->toBe([]);
    });

    test('can get theme template paths for child themes', function (): void {
        Brain\Monkey\Functions\when('is_child_theme')->justReturn(true);
        Brain\Monkey\Functions\when('get_template_directory')->justReturn('/themes/parent');

        $mockWC = Mockery::mock();
        $mockWC->shouldReceive('template_path')->andReturn('woocommerce/');
        Brain\Monkey\Functions\when('WC')->justReturn($mockWC);

        $paths = $this->service->getThemeTemplatePaths();

        expect($paths)->toContain('/themes/parent/woocommerce/');
    });

    test('returns empty array for non-child themes', function (): void {
        Brain\Monkey\Functions\when('is_child_theme')->justReturn(false);

        $paths = $this->service->getThemeTemplatePaths();

        expect($paths)->toBe([]);
    });

    test('can detect woocommerce status screen', function (): void {
        $screen = new stdClass;
        $screen->id = 'woocommerce_page_wc-status';

        $result = $this->service->isWooCommerceStatusScreen(true, false, $screen);

        expect($result)->toBeTrue();
    });

    test('returns false when not on woocommerce status screen', function (): void {
        $screen = new stdClass;
        $screen->id = 'edit-post';

        $result = $this->service->isWooCommerceStatusScreen(true, false, $screen);

        expect($result)->toBeFalse();
    });

    test('returns false when doing ajax', function (): void {
        $screen = new stdClass;
        $screen->id = 'woocommerce_page_wc-status';

        $result = $this->service->isWooCommerceStatusScreen(true, true, $screen);

        expect($result)->toBeFalse();
    });

    test('returns false when not in admin', function (): void {
        $screen = new stdClass;
        $screen->id = 'woocommerce_page_wc-status';

        $result = $this->service->isWooCommerceStatusScreen(false, false, $screen);

        expect($result)->toBeFalse();
    });

    test('can get woocommerce template path with WC available', function (): void {
        $mockWC = Mockery::mock();
        $mockWC->shouldReceive('template_path')->andReturn('woocommerce/');
        Brain\Monkey\Functions\when('WC')->justReturn($mockWC);

        $path = $this->service->getWooCommerceTemplatePath();

        expect($path)->toBe('woocommerce/');
    });

    test('returns default path when WC not available', function (): void {
        Brain\Monkey\Functions\when('WC')->justReturn();

        $path = $this->service->getWooCommerceTemplatePath();

        expect($path)->toBe('woocommerce/');
    });

    test('can get all template paths', function (): void {
        if (! defined('WC_ABSPATH')) {
            define('WC_ABSPATH', '/plugin/woocommerce/');
        }

        Brain\Monkey\Functions\when('is_child_theme')->justReturn(true);
        Brain\Monkey\Functions\when('get_template_directory')->justReturn('/themes/parent');

        $mockWC = Mockery::mock();
        $mockWC->shouldReceive('template_path')->andReturn('woocommerce/');
        Brain\Monkey\Functions\when('WC')->justReturn($mockWC);

        $paths = $this->service->getAllTemplatePaths();

        expect($paths)->toContain('/plugin/woocommerce/templates/');
        expect($paths)->toContain('/themes/parent/woocommerce/');
    });

    test('can create template from path', function (): void {
        $template = $this->service->createTemplate('/path/to/single-product.php');

        expect($template)->toBeInstanceOf(Template::class);
        expect($template->path)->toBe('/path/to/single-product.php');
    });

    test('can add blade variants to template list', function (): void {
        $templates = [
            'single-product.php',
            'archive-product.php',
            'style.css',
        ];

        $result = $this->service->addBladeVariants($templates);

        expect($result)->toContain('single-product.blade.php');
        expect($result)->toContain('archive-product.blade.php');
        expect($result)->toContain('single-product.php');
        expect($result)->toContain('archive-product.php');
        expect($result)->toContain('style.css');
        expect($result)->not->toContain('style.blade.css');
    });

    test('does not duplicate existing blade templates', function (): void {
        $templates = [
            'single-product.blade.php',
            'archive-product.php',
        ];

        $result = $this->service->addBladeVariants($templates);

        expect($result)->toContain('archive-product.blade.php');
        expect($result)->toContain('single-product.blade.php');
        expect($result)->toContain('archive-product.php');
        expect(array_count_values($result)['single-product.blade.php'])->toBe(1);
    });

    test('handles empty template list', function (): void {
        $templates = [];

        $result = $this->service->addBladeVariants($templates);

        expect($result)->toBe([]);
    });
});
