<?php

declare(strict_types=1);

use Illuminate\Contracts\View\Factory as ViewFactory;
use Pollora\ThirdParty\WooCommerce\Domain\Models\Template;
use Pollora\ThirdParty\WooCommerce\Domain\Services\WooCommerceService;
use Pollora\ThirdParty\WooCommerce\Infrastructure\Adapters\WordPressWooCommerceAdapter;
use Pollora\ThirdParty\WooCommerce\Infrastructure\Services\WooCommerce;
use Pollora\View\Domain\Contracts\TemplateFinderInterface;

beforeEach(function (): void {
    $this->templateFinder = Mockery::mock(TemplateFinderInterface::class);
    $this->viewFactory = Mockery::mock(ViewFactory::class);
    $this->domainService = Mockery::mock(WooCommerceService::class);
    $this->adapter = Mockery::mock(WordPressWooCommerceAdapter::class);

    $this->wc = new WooCommerce(
        $this->templateFinder,
        $this->viewFactory,
        $this->domainService,
        $this->adapter
    );
});

describe('WooCommerce::template()', function (): void {
    it('returns original template when no theme template found', function (): void {
        $this->domainService->shouldReceive('getWooCommerceTemplatePath')->andReturn('woocommerce/');
        $this->domainService->shouldReceive('createTemplate')->andReturn(
            Template::fromPath('/wc/templates/single-product.php')
        );
        $this->domainService->shouldReceive('getAllTemplatePaths')->andReturn(['/wc/templates/']);
        $this->templateFinder->shouldReceive('locate')->andReturn([]);

        $result = $this->wc->template('/wc/templates/single-product.php', 'single-product.php');

        expect($result)->toBe('/wc/templates/single-product.php');
    });

    it('returns non-blade theme template directly', function (): void {
        $this->domainService->shouldReceive('getWooCommerceTemplatePath')->andReturn('woocommerce/');
        $this->domainService->shouldReceive('createTemplate')->andReturn(
            Template::fromPath('/wc/templates/single-product.php')
        );
        $this->domainService->shouldReceive('getAllTemplatePaths')->andReturn(['/wc/templates/']);
        $this->domainService->shouldReceive('isWooCommerceStatusScreen')->andReturn(false);
        $this->templateFinder->shouldReceive('locate')->andReturn(['woocommerce/single-product.php']);
        $this->adapter->shouldReceive('locateTemplate')->andReturn('/theme/woocommerce/single-product.php');
        $this->adapter->shouldReceive('isAdmin')->andReturn(false);
        $this->adapter->shouldReceive('isDoingAjax')->andReturn(false);
        $this->adapter->shouldReceive('getCurrentScreen')->andReturn(null);

        $result = $this->wc->template('/wc/templates/single-product.php', 'single-product.php');

        expect($result)->toBe('/theme/woocommerce/single-product.php');
    });

    it('caches template resolution results per request', function (): void {
        $this->domainService->shouldReceive('getWooCommerceTemplatePath')->once()->andReturn('woocommerce/');
        $this->domainService->shouldReceive('createTemplate')->once()->andReturn(
            Template::fromPath('/wc/templates/single-product.php')
        );
        $this->domainService->shouldReceive('getAllTemplatePaths')->once()->andReturn(['/wc/templates/']);
        $this->templateFinder->shouldReceive('locate')->once()->andReturn([]);

        // First call — resolves normally
        $result1 = $this->wc->template('/wc/templates/single-product.php', 'single-product.php');
        // Second call — should return cached result without calling dependencies again
        $result2 = $this->wc->template('/wc/templates/single-product.php', 'single-product.php');

        expect($result1)->toBe($result2);
    });

    it('caches different results for different template names', function (): void {
        // Setup for first template
        $this->domainService->shouldReceive('getWooCommerceTemplatePath')->andReturn('woocommerce/');
        $this->domainService->shouldReceive('getAllTemplatePaths')->andReturn(['/wc/templates/']);

        $this->domainService->shouldReceive('createTemplate')
            ->with('/wc/templates/single-product.php')
            ->andReturn(Template::fromPath('/wc/templates/single-product.php'));
        $this->domainService->shouldReceive('createTemplate')
            ->with('/wc/templates/archive-product.php')
            ->andReturn(Template::fromPath('/wc/templates/archive-product.php'));

        $this->templateFinder->shouldReceive('locate')->andReturn([]);

        $result1 = $this->wc->template('/wc/templates/single-product.php');
        $result2 = $this->wc->template('/wc/templates/archive-product.php');

        expect($result1)->toBe('/wc/templates/single-product.php');
        expect($result2)->toBe('/wc/templates/archive-product.php');
    });
});

describe('WooCommerce::determineTemplateToLocate()', function (): void {
    it('prioritizes template with path over templateName', function (): void {
        // When $template contains a path, it should be used regardless of $templateName
        $this->domainService->shouldReceive('getWooCommerceTemplatePath')->andReturn('woocommerce/');
        $this->domainService->shouldReceive('createTemplate')
            ->with('/full/path/to/template.php')
            ->andReturn(Template::fromPath('/full/path/to/template.php'));
        $this->domainService->shouldReceive('getAllTemplatePaths')->andReturn([]);
        $this->templateFinder->shouldReceive('locate')->andReturn([]);

        $result = $this->wc->template('/full/path/to/template.php', 'different-name.php');

        // Should use /full/path since it contains '/'
        expect($result)->toBe('/full/path/to/template.php');
    });
});
