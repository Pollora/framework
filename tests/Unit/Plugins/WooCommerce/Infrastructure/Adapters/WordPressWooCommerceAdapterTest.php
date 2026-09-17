<?php

declare(strict_types=1);

use Pollora\ThirdParty\WooCommerce\Infrastructure\Adapters\WordPressWooCommerceAdapter;

describe('WordPressWooCommerceAdapter', function (): void {
    beforeEach(function (): void {
        $this->adapter = new WordPressWooCommerceAdapter;
    });

    test('can locate template using wordpress function', function (): void {
        Brain\Monkey\Functions\when('locate_template')->alias(function ($templates, $load, $requireOnce): string {
            expect($templates)->toBe('single-product.php');
            expect($load)->toBeFalse();
            expect($requireOnce)->toBeTrue();

            return '/theme/single-product.php';
        });

        $result = $this->adapter->locateTemplate('single-product.php');

        expect($result)->toBe('/theme/single-product.php');
    });

    test('can locate template with array of templates', function (): void {
        Brain\Monkey\Functions\when('locate_template')->alias(function ($templates, $load, $requireOnce): string {
            expect($templates)->toBe(['single-product.blade.php', 'single-product.php']);

            return '/theme/single-product.php';
        });

        $result = $this->adapter->locateTemplate(['single-product.blade.php', 'single-product.php']);

        expect($result)->toBe('/theme/single-product.php');
    });

    test('returns empty string when locate_template function not available', function (): void {
        $adapter = new WordPressWooCommerceAdapter;

        $result = $adapter->locateTemplate('single-product.php');

        expect($result)->toBe('');
    });

    test('can add theme support', function (): void {
        Brain\Monkey\Functions\when('add_theme_support')->alias(function ($feature, $options = null): true {
            expect($feature)->toBe('woocommerce');
            expect($options)->toBeNull();

            return true;
        });

        $result = $this->adapter->addThemeSupport('woocommerce');

        expect($result)->toBeTrue();
    });

    test('can add theme support with options', function (): void {
        Brain\Monkey\Functions\when('add_theme_support')->alias(function ($feature, $options = null): true {
            expect($feature)->toBe('woocommerce');
            expect($options)->toBe(['gallery_thumbnail_image_width' => 150]);

            return true;
        });

        $result = $this->adapter->addThemeSupport('woocommerce', ['gallery_thumbnail_image_width' => 150]);

        expect($result)->toBeTrue();
    });

    test('can detect child theme', function (): void {
        Brain\Monkey\Functions\when('is_child_theme')->justReturn(true);

        $result = $this->adapter->isChildTheme();

        expect($result)->toBeTrue();
    });

    test('returns false when not child theme', function (): void {
        Brain\Monkey\Functions\when('is_child_theme')->justReturn(false);

        $result = $this->adapter->isChildTheme();

        expect($result)->toBeFalse();
    });

    test('can get stylesheet directory', function (): void {
        Brain\Monkey\Functions\when('get_stylesheet_directory')->justReturn('/themes/child');

        $result = $this->adapter->getStylesheetDirectory();

        expect($result)->toBe('/themes/child');
    });

    test('can get template directory', function (): void {
        Brain\Monkey\Functions\when('get_template_directory')->justReturn('/themes/parent');

        $result = $this->adapter->getTemplateDirectory();

        expect($result)->toBe('/themes/parent');
    });

    test('can detect admin area', function (): void {
        Brain\Monkey\Functions\when('is_admin')->justReturn(true);

        $result = $this->adapter->isAdmin();

        expect($result)->toBeTrue();
    });

    test('can detect ajax request', function (): void {
        Brain\Monkey\Functions\when('wp_doing_ajax')->justReturn(true);

        $result = $this->adapter->isDoingAjax();

        expect($result)->toBeTrue();
    });

    test('can get current screen', function (): void {
        $expectedScreen = new WP_Screen;
        $expectedScreen->id = 'woocommerce_page_wc-status';

        Brain\Monkey\Functions\when('get_current_screen')->justReturn($expectedScreen);

        $result = $this->adapter->getCurrentScreen();

        expect($result)->toBe($expectedScreen);
    });

    test('can detect doing action', function (): void {
        Brain\Monkey\Functions\when('doing_action')->alias(function ($action): true {
            expect($action)->toBe('after_setup_theme');

            return true;
        });

        $result = $this->adapter->isDoingAction('after_setup_theme');

        expect($result)->toBeTrue();
    });

    test('can get woocommerce template path', function (): void {
        $mockWC = Mockery::mock();
        $mockWC->shouldReceive('template_path')->andReturn('woocommerce/');
        Brain\Monkey\Functions\when('WC')->justReturn($mockWC);

        $result = $this->adapter->getWooCommerceTemplatePath();

        expect($result)->toBe('woocommerce/');
    });

    test('returns default template path when WC not available', function (): void {
        Brain\Monkey\Functions\when('WC')->justReturn();

        $result = $this->adapter->getWooCommerceTemplatePath();

        expect($result)->toBe('woocommerce/');
    });

    test('can apply filters', function (): void {
        Brain\Monkey\Functions\when('apply_filters')->alias(function ($hook, $value, ...$args): array {
            expect($hook)->toBe('pollora/woocommerce/template_paths');
            expect($value)->toBe(['/default/path/']);
            expect($args)->toBe(['extra', 'args']);

            return ['/default/path/', '/custom/path/'];
        });

        $result = $this->adapter->applyFilters('pollora/woocommerce/template_paths', ['/default/path/'], 'extra', 'args');

        expect($result)->toBe(['/default/path/', '/custom/path/']);
    });

    test('returns original value when apply_filters not available', function (): void {
        $adapter = new WordPressWooCommerceAdapter;

        $result = $adapter->applyFilters('test_hook', 'test_value');

        expect($result)->toBe('test_value');
    });

    test('can detect woocommerce availability', function (): void {
        if (! defined('WC_ABSPATH')) {
            define('WC_ABSPATH', '/plugin/woocommerce/');
        }

        Brain\Monkey\Functions\when('WC')->justReturn(new stdClass);

        $result = $this->adapter->isWooCommerceAvailable();

        expect($result)->toBeTrue();
    });
});
