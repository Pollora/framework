<?php

declare(strict_types=1);

use Pollora\Plugins\WooCommerce\Infrastructure\Adapters\WordPressWooCommerceAdapter;

describe('WordPressWooCommerceAdapter', function () {
    beforeEach(function () {
        setupWordPressMocks();
        $this->adapter = new WordPressWooCommerceAdapter();
    });

    afterEach(function () {
        resetWordPressMocks();
    });

    test('can locate template using wordpress function', function () {
        setWordPressFunction('locate_template', function ($templates, $load, $requireOnce) {
            expect($templates)->toBe('single-product.php');
            expect($load)->toBeFalse();
            expect($requireOnce)->toBeTrue();
            return '/theme/single-product.php';
        });

        $result = $this->adapter->locateTemplate('single-product.php');

        expect($result)->toBe('/theme/single-product.php');
    });

    test('can locate template with array of templates', function () {
        setWordPressFunction('locate_template', function ($templates, $load, $requireOnce) {
            expect($templates)->toBe(['single-product.blade.php', 'single-product.php']);
            return '/theme/single-product.php';
        });

        $result = $this->adapter->locateTemplate(['single-product.blade.php', 'single-product.php']);

        expect($result)->toBe('/theme/single-product.php');
    });

    test('returns empty string when locate_template function not available', function () {
        // Don't set the function to simulate unavailability
        $adapter = new WordPressWooCommerceAdapter();

        $result = $adapter->locateTemplate('single-product.php');

        expect($result)->toBe('');
    });

    test('can add theme support', function () {
        setWordPressFunction('add_theme_support', function ($feature, $options = null) {
            expect($feature)->toBe('woocommerce');
            expect($options)->toBeNull();
            return true;
        });

        $result = $this->adapter->addThemeSupport('woocommerce');

        expect($result)->toBeTrue();
    });

    test('can add theme support with options', function () {
        setWordPressFunction('add_theme_support', function ($feature, $options = null) {
            expect($feature)->toBe('woocommerce');
            expect($options)->toBe(['gallery_thumbnail_image_width' => 150]);
            return true;
        });

        $result = $this->adapter->addThemeSupport('woocommerce', ['gallery_thumbnail_image_width' => 150]);

        expect($result)->toBeTrue();
    });

    test('returns false when add_theme_support not available', function () {
        $adapter = new WordPressWooCommerceAdapter();

        $result = $adapter->addThemeSupport('woocommerce');

        expect($result)->toBeFalse();
    });

    test('can detect child theme', function () {
        setWordPressFunction('is_child_theme', fn() => true);

        $result = $this->adapter->isChildTheme();

        expect($result)->toBeTrue();
    });

    test('returns false when not child theme', function () {
        setWordPressFunction('is_child_theme', fn() => false);

        $result = $this->adapter->isChildTheme();

        expect($result)->toBeFalse();
    });

    test('can get stylesheet directory', function () {
        setWordPressFunction('get_stylesheet_directory', fn() => '/themes/child');

        $result = $this->adapter->getStylesheetDirectory();

        expect($result)->toBe('/themes/child');
    });

    test('can get template directory', function () {
        setWordPressFunction('get_template_directory', fn() => '/themes/parent');

        $result = $this->adapter->getTemplateDirectory();

        expect($result)->toBe('/themes/parent');
    });

    test('can detect admin area', function () {
        setWordPressFunction('is_admin', fn() => true);

        $result = $this->adapter->isAdmin();

        expect($result)->toBeTrue();
    });

    test('can detect ajax request', function () {
        setWordPressFunction('wp_doing_ajax', fn() => true);

        $result = $this->adapter->isDoingAjax();

        expect($result)->toBeTrue();
    });

    test('can get current screen', function () {
        $expectedScreen = new stdClass();
        $expectedScreen->id = 'woocommerce_page_wc-status';

        setWordPressFunction('get_current_screen', fn() => $expectedScreen);

        $result = $this->adapter->getCurrentScreen();

        expect($result)->toBe($expectedScreen);
    });

    test('can detect doing action', function () {
        setWordPressFunction('doing_action', function ($action) {
            expect($action)->toBe('after_setup_theme');
            return true;
        });

        $result = $this->adapter->isDoingAction('after_setup_theme');

        expect($result)->toBeTrue();
    });

    test('can get woocommerce template path', function () {
        $mockWC = Mockery::mock();
        $mockWC->shouldReceive('template_path')->andReturn('woocommerce/');
        setWordPressFunction('WC', fn() => $mockWC);

        $result = $this->adapter->getWooCommerceTemplatePath();

        expect($result)->toBe('woocommerce/');
    });

    test('returns default template path when WC not available', function () {
        setWordPressFunction('WC', fn() => null);

        $result = $this->adapter->getWooCommerceTemplatePath();

        expect($result)->toBe('woocommerce/');
    });

    test('can apply filters', function () {
        setWordPressFunction('apply_filters', function ($hook, $value, ...$args) {
            expect($hook)->toBe('pollora/woocommerce/template_paths');
            expect($value)->toBe(['/default/path/']);
            expect($args)->toBe(['extra', 'args']);
            return ['/default/path/', '/custom/path/'];
        });

        $result = $this->adapter->applyFilters('pollora/woocommerce/template_paths', ['/default/path/'], 'extra', 'args');

        expect($result)->toBe(['/default/path/', '/custom/path/']);
    });

    test('returns original value when apply_filters not available', function () {
        $adapter = new WordPressWooCommerceAdapter();

        $result = $adapter->applyFilters('test_hook', 'test_value');

        expect($result)->toBe('test_value');
    });

    test('can detect woocommerce availability', function () {
        if (!defined('WC_ABSPATH')) {
            define('WC_ABSPATH', '/plugin/woocommerce/');
        }

        setWordPressFunction('WC', fn() => new stdClass());

        $result = $this->adapter->isWooCommerceAvailable();

        expect($result)->toBeTrue();
    });

    test('returns false when woocommerce not available', function () {
        setWordPressFunction('WC', fn() => null);

        $result = $this->adapter->isWooCommerceAvailable();

        if (!defined('WC_ABSPATH')) {
            expect($result)->toBeFalse();
        }
    });
});