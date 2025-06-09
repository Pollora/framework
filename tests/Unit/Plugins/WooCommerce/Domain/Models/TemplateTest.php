<?php

declare(strict_types=1);

use Pollora\Plugins\WooCommerce\Domain\Models\Template;

describe('Template', function () {
    test('can be created with basic parameters', function () {
        $template = new Template('/path/to/template.php', 'template', false);

        expect($template->path)->toBe('/path/to/template.php');
        expect($template->name)->toBe('template');
        expect($template->isBladeTemplate)->toBeFalse();
    });

    test('can be created from path', function () {
        $template = Template::fromPath('/path/to/single-product.php');

        expect($template->path)->toBe('/path/to/single-product.php');
        expect($template->name)->toBe('single-product');
        expect($template->isBladeTemplate)->toBeFalse();
    });

    test('can detect blade templates when created from path', function () {
        $template = Template::fromPath('/path/to/single-product.blade.php');

        expect($template->path)->toBe('/path/to/single-product.blade.php');
        expect($template->name)->toBe('single-product.blade');
        expect($template->isBladeTemplate)->toBeTrue();
    });

    test('can get relative path', function () {
        $template = new Template('/plugin/templates/single-product.php');
        $defaultPaths = ['/plugin/templates/'];

        $relativePath = $template->getRelativePath($defaultPaths);

        expect($relativePath)->toBe('single-product.php');
    });

    test('returns original path when no default paths match', function () {
        $template = new Template('/theme/templates/single-product.php');
        $defaultPaths = ['/plugin/templates/'];

        $relativePath = $template->getRelativePath($defaultPaths);

        expect($relativePath)->toBe('/theme/templates/single-product.php');
    });

    test('can detect woocommerce template', function () {
        $template = new Template('/plugin/templates/single-product.php');
        $defaultPaths = ['/plugin/templates/'];

        expect($template->isWooCommerceTemplate($defaultPaths))->toBeTrue();
    });

    test('can detect non-woocommerce template', function () {
        $template = new Template('/theme/templates/single-product.php');
        $defaultPaths = ['/plugin/templates/'];

        expect($template->isWooCommerceTemplate($defaultPaths))->toBeFalse();
    });

    test('can convert to blade template', function () {
        $template = new Template('/path/to/single-product.php', 'single-product', false);

        $bladeTemplate = $template->toBladeTemplate();

        expect($bladeTemplate->path)->toBe('/path/to/single-product.blade.php');
        expect($bladeTemplate->name)->toBe('single-product');
        expect($bladeTemplate->isBladeTemplate)->toBeTrue();
    });

    test('blade template conversion is idempotent', function () {
        $template = new Template('/path/to/single-product.blade.php', 'single-product', true);

        $bladeTemplate = $template->toBladeTemplate();

        expect($bladeTemplate->path)->toBe('/path/to/single-product.blade.php');
        expect($bladeTemplate->name)->toBe('single-product');
        expect($bladeTemplate->isBladeTemplate)->toBeTrue();
        expect($bladeTemplate)->toBe($template);
    });

    test('non-php files are not converted to blade', function () {
        $template = new Template('/path/to/style.css', 'style', false);

        $bladeTemplate = $template->toBladeTemplate();

        expect($bladeTemplate)->toBe($template);
    });

    test('can get view name for blade templates', function () {
        $template = new Template('woocommerce/single-product.blade.php', 'single-product', true);

        $viewName = $template->getViewName();

        expect($viewName)->toBe('woocommerce.single-product');
    });

    test('returns empty view name for non-blade templates', function () {
        $template = new Template('woocommerce/single-product.php', 'single-product', false);

        $viewName = $template->getViewName();

        expect($viewName)->toBe('');
    });

    test('handles complex path in view name', function () {
        $template = new Template('woocommerce/cart/cart.blade.php', 'cart', true);

        $viewName = $template->getViewName();

        expect($viewName)->toBe('woocommerce.cart.cart');
    });

    test('trims dots from view name', function () {
        $template = new Template('/single-product.blade.php', 'single-product', true);

        $viewName = $template->getViewName();

        expect($viewName)->toBe('single-product');
    });
});
