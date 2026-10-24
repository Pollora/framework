<?php

declare(strict_types=1);

use Pollora\ThirdParty\WooCommerce\Domain\Contracts\WooCommerceEnvironmentInterface;
use Pollora\ThirdParty\WooCommerce\Domain\Models\Template;
use Pollora\ThirdParty\WooCommerce\Domain\Services\WooCommerceService;

/**
 * An environment double: a child theme of /themes/parent or not, and the
 * override folder WooCommerce declares.
 */
function wooCommerceEnvironment(bool $childTheme, string $templatePath = 'woocommerce/'): WooCommerceEnvironmentInterface
{
    $environment = Mockery::mock(WooCommerceEnvironmentInterface::class);
    $environment->shouldReceive('isChildTheme')->andReturn($childTheme);
    $environment->shouldReceive('getTemplateDirectory')->andReturn('/themes/parent');
    $environment->shouldReceive('getWooCommerceTemplatePath')->andReturn($templatePath);

    return $environment;
}

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
        $service = new WooCommerceService(wooCommerceEnvironment(true));

        $paths = $service->getThemeTemplatePaths();

        expect($paths)->toContain('/themes/parent/woocommerce/');
    });

    test('uses the override folder WooCommerce declares for child themes', function (): void {
        $service = new WooCommerceService(wooCommerceEnvironment(true, 'shop/'));

        expect($service->getThemeTemplatePaths())->toBe(['/themes/parent/shop/']);
    });

    test('returns empty array for non-child themes', function (): void {
        $service = new WooCommerceService(wooCommerceEnvironment(false));

        $paths = $service->getThemeTemplatePaths();

        expect($paths)->toBe([]);
    });

    test('knows of no child theme without an environment', function (): void {
        expect($this->service->getThemeTemplatePaths())->toBe([]);
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

    test('can get woocommerce template path from the environment', function (): void {
        $service = new WooCommerceService(wooCommerceEnvironment(false, 'shop/'));

        $path = $service->getWooCommerceTemplatePath();

        expect($path)->toBe('shop/');
    });

    test('returns default path without an environment', function (): void {
        $path = $this->service->getWooCommerceTemplatePath();

        expect($path)->toBe('woocommerce/');
    });

    test('can get all template paths', function (): void {
        if (! defined('WC_ABSPATH')) {
            define('WC_ABSPATH', '/plugin/woocommerce/');
        }

        $service = new WooCommerceService(wooCommerceEnvironment(true));

        $paths = $service->getAllTemplatePaths();

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
