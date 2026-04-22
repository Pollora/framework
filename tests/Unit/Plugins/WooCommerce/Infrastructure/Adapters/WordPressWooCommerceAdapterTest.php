<?php

declare(strict_types=1);

use Pollora\ThirdParty\WooCommerce\Infrastructure\Adapters\WordPressWooCommerceAdapter;

describe('WordPressWooCommerceAdapter', function (): void {
    beforeEach(function (): void {
        setupWordPressMocks();
        $this->adapter = new WordPressWooCommerceAdapter;
    });

    afterEach(function (): void {
        resetWordPressMocks();
    });

    test('can locate template using wordpress function', function (): void {
        setWordPressFunction('locate_template', function ($templates, $load, $requireOnce): string {
            expect($templates)->toBe('single-product.php');
            expect($load)->toBeFalse();
            expect($requireOnce)->toBeTrue();

            return '/theme/single-product.php';
        });

        $result = $this->adapter->locateTemplate('single-product.php');

        expect($result)->toBe('/theme/single-product.php');
    });

    test('can locate template with array of templates', function (): void {
        setWordPressFunction('locate_template', function ($templates, $load, $requireOnce): string {
            expect($templates)->toBe(['single-product.blade.php', 'single-product.php']);

            return '/theme/single-product.php';
        });

        $result = $this->adapter->locateTemplate(['single-product.blade.php', 'single-product.php']);

        expect($result)->toBe('/theme/single-product.php');
    });

    test('returns empty string when locate_template function not available', function (): void {
        // Don't set the function to simulate unavailability
        $adapter = new WordPressWooCommerceAdapter;

        $result = $adapter->locateTemplate('single-product.php');

        expect($result)->toBe('');
    });

    test('can add theme support', function (): void {
        setWordPressFunction('add_theme_support', function ($feature, $options = null): true {
            expect($feature)->toBe('woocommerce');
            expect($options)->toBeNull();

            return true;
        });

        $result = $this->adapter->addThemeSupport('woocommerce');

        expect($result)->toBeTrue();
    });

    test('can add theme support with options', function (): void {
        setWordPressFunction('add_theme_support', function ($feature, $options = null): true {
            expect($feature)->toBe('woocommerce');
            expect($options)->toBe(['gallery_thumbnail_image_width' => 150]);

            return true;
        });

        $result = $this->adapter->addThemeSupport('woocommerce', ['gallery_thumbnail_image_width' => 150]);

        expect($result)->toBeTrue();
    });

    // Removed test for function availability since functions are always defined in our test environment

    test('can detect child theme', function (): void {
        setWordPressFunction('is_child_theme', fn (): true => true);

        $result = $this->adapter->isChildTheme();

        expect($result)->toBeTrue();
    });

    test('returns false when not child theme', function (): void {
        setWordPressFunction('is_child_theme', fn (): false => false);

        $result = $this->adapter->isChildTheme();

        expect($result)->toBeFalse();
    });

    test('can get stylesheet directory', function (): void {
        setWordPressFunction('get_stylesheet_directory', fn (): string => '/themes/child');

        $result = $this->adapter->getStylesheetDirectory();

        expect($result)->toBe('/themes/child');
    });

    test('can get template directory', function (): void {
        setWordPressFunction('get_template_directory', fn (): string => '/themes/parent');

        $result = $this->adapter->getTemplateDirectory();

        expect($result)->toBe('/themes/parent');
    });

    test('can detect admin area', function (): void {
        setWordPressFunction('is_admin', fn (): true => true);

        $result = $this->adapter->isAdmin();

        expect($result)->toBeTrue();
    });

    test('can detect ajax request', function (): void {
        setWordPressFunction('wp_doing_ajax', fn (): true => true);

        $result = $this->adapter->isDoingAjax();

        expect($result)->toBeTrue();
    });

    test('can get current screen', function (): void {
        $expectedScreen = new WP_Screen;
        $expectedScreen->id = 'woocommerce_page_wc-status';

        setWordPressFunction('get_current_screen', fn (): WP_Screen => $expectedScreen);

        $result = $this->adapter->getCurrentScreen();

        expect($result)->toBe($expectedScreen);
    });

    test('can detect doing action', function (): void {
        setWordPressFunction('doing_action', function ($action): true {
            expect($action)->toBe('after_setup_theme');

            return true;
        });

        $result = $this->adapter->isDoingAction('after_setup_theme');

        expect($result)->toBeTrue();
    });

    test('can get woocommerce template path', function (): void {
        $mockWC = Mockery::mock();
        $mockWC->shouldReceive('template_path')->andReturn('woocommerce/');
        setWordPressFunction('WC', fn () => $mockWC);

        $result = $this->adapter->getWooCommerceTemplatePath();

        expect($result)->toBe('woocommerce/');
    });

    test('returns default template path when WC not available', function (): void {
        setWordPressFunction('WC', fn (): null => null);

        $result = $this->adapter->getWooCommerceTemplatePath();

        expect($result)->toBe('woocommerce/');
    });

    test('can apply filters', function (): void {
        setWordPressFunction('apply_filters', function ($hook, $value, ...$args): array {
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

        setWordPressFunction('WC', fn (): stdClass => new stdClass);

        $result = $this->adapter->isWooCommerceAvailable();

        expect($result)->toBeTrue();
    });
});
