<?php

declare(strict_types=1);

use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Contracts\View\View;
use Mockery\MockInterface;
use Pollora\Plugins\WooCommerce\Domain\Services\WooCommerceService;
use Pollora\Plugins\WooCommerce\Infrastructure\Adapters\WordPressWooCommerceAdapter;
use Pollora\Plugins\WooCommerce\Infrastructure\Services\WooCommerce;
use Pollora\View\Domain\Contracts\TemplateFinderInterface;

describe('WooCommerce', function () {
    beforeEach(function () {
        setupWordPressMocks();

        $this->container = Mockery::mock(Container::class);
        $this->templateFinder = Mockery::mock(TemplateFinderInterface::class);
        $this->viewFactory = Mockery::mock(ViewFactory::class);
        $this->domainService = Mockery::mock(WooCommerceService::class);
        $this->adapter = Mockery::mock(WordPressWooCommerceAdapter::class);

        $this->woocommerce = new WooCommerce(
            $this->container,
            $this->templateFinder,
            $this->viewFactory,
            $this->domainService,
            $this->adapter
        );
    });

    afterEach(function () {
        resetWordPressMocks();
        Mockery::close();
    });

    test('can load theme template hooks', function () {
        $this->adapter->shouldReceive('locateTemplate')
            ->once()
            ->with('wc-template-hooks.php', true, true);

        $this->woocommerce->loadThemeTemplateHooks();
    });

    test('can add theme support', function () {
        $this->adapter->shouldReceive('addThemeSupport')
            ->once()
            ->with('woocommerce');

        $this->woocommerce->addThemeSupport();
    });

    test('can handle reviews template for woocommerce templates', function () {
        $templatePath = '/plugin/templates/single-product-reviews.php';

        $this->domainService->shouldReceive('createTemplate')
            ->once()
            ->with($templatePath)
            ->andReturn(Mockery::mock()->shouldReceive('isWooCommerceTemplate')->with([])->andReturn(true)->getMock());

        $this->domainService->shouldReceive('getAllTemplatePaths')
            ->once()
            ->andReturn([]);

        $this->domainService->shouldReceive('isWooCommerceStatusScreen')
            ->once()
            ->andReturn(false);

        $this->domainService->shouldReceive('getWooCommerceTemplatePath')
            ->once()
            ->andReturn('woocommerce/');

        $templateMock = Mockery::mock();
        $templateMock->shouldReceive('getRelativePath')
            ->with([])
            ->andReturn('single-product-reviews.php');

        $this->domainService->shouldReceive('createTemplate')
            ->with($templatePath)
            ->andReturn($templateMock);

        $this->templateFinder->shouldReceive('locate')
            ->once()
            ->with('woocommerce/single-product-reviews.php')
            ->andReturn([]);

        $result = $this->woocommerce->reviewsTemplate($templatePath);

        expect($result)->toBe($templatePath);
    });

    test('returns original template for non-woocommerce templates in reviews', function () {
        $templatePath = '/theme/comments.php';

        $this->domainService->shouldReceive('createTemplate')
            ->once()
            ->with($templatePath)
            ->andReturn(Mockery::mock()->shouldReceive('isWooCommerceTemplate')->with([])->andReturn(false)->getMock());

        $this->domainService->shouldReceive('getAllTemplatePaths')
            ->once()
            ->andReturn([]);

        $result = $this->woocommerce->reviewsTemplate($templatePath);

        expect($result)->toBe($templatePath);
    });

    test('can handle template processing for blade templates', function () {
        $templatePath = '/plugin/templates/single-product.php';
        $themeTemplatePath = '/theme/woocommerce/single-product.blade.php';
        $viewName = 'woocommerce.single-product';

        $this->domainService->shouldReceive('isWooCommerceStatusScreen')
            ->once()
            ->andReturn(false);

        $this->domainService->shouldReceive('getWooCommerceTemplatePath')
            ->once()
            ->andReturn('woocommerce/');

        $templateMock = Mockery::mock();
        $templateMock->shouldReceive('getRelativePath')
            ->with([])
            ->andReturn('single-product.php');

        $this->domainService->shouldReceive('createTemplate')
            ->with($templatePath)
            ->andReturn($templateMock);

        $this->domainService->shouldReceive('getAllTemplatePaths')
            ->once()
            ->andReturn([]);

        $this->templateFinder->shouldReceive('locate')
            ->once()
            ->with('woocommerce/single-product.php')
            ->andReturn([$themeTemplatePath]);

        $this->adapter->shouldReceive('locateTemplate')
            ->once()
            ->with([$themeTemplatePath])
            ->andReturn($themeTemplatePath);

        $this->templateFinder->shouldReceive('getViewNameFromPath')
            ->once()
            ->with($themeTemplatePath)
            ->andReturn($viewName);

        $this->viewFactory->shouldReceive('exists')
            ->once()
            ->with($viewName)
            ->andReturn(true);

        $view = Mockery::mock(View::class);
        $view->shouldReceive('makeLoader')
            ->once()
            ->andReturn('/cache/compiled/template.php');

        $this->viewFactory->shouldReceive('make')
            ->once()
            ->with($viewName)
            ->andReturn($view);

        $result = $this->woocommerce->template($templatePath);

        expect($result)->toBe('/cache/compiled/template.php');
    });

    test('can handle template processing for non-blade templates', function () {
        $templatePath = '/plugin/templates/single-product.php';
        $themeTemplatePath = '/theme/woocommerce/single-product.php';

        $this->domainService->shouldReceive('isWooCommerceStatusScreen')
            ->once()
            ->andReturn(false);

        $this->domainService->shouldReceive('getWooCommerceTemplatePath')
            ->once()
            ->andReturn('woocommerce/');

        $templateMock = Mockery::mock();
        $templateMock->shouldReceive('getRelativePath')
            ->with([])
            ->andReturn('single-product.php');

        $this->domainService->shouldReceive('createTemplate')
            ->with($templatePath)
            ->andReturn($templateMock);

        $this->domainService->shouldReceive('getAllTemplatePaths')
            ->once()
            ->andReturn([]);

        $this->templateFinder->shouldReceive('locate')
            ->once()
            ->with('woocommerce/single-product.php')
            ->andReturn([$themeTemplatePath]);

        $this->adapter->shouldReceive('locateTemplate')
            ->once()
            ->with([$themeTemplatePath])
            ->andReturn($themeTemplatePath);

        $result = $this->woocommerce->template($templatePath);

        expect($result)->toBe($themeTemplatePath);
    });

    test('returns original template when no theme template found', function () {
        $templatePath = '/plugin/templates/single-product.php';

        $this->domainService->shouldReceive('getWooCommerceTemplatePath')
            ->once()
            ->andReturn('woocommerce/');

        $templateMock = Mockery::mock();
        $templateMock->shouldReceive('getRelativePath')
            ->with([])
            ->andReturn('single-product.php');

        $this->domainService->shouldReceive('createTemplate')
            ->with($templatePath)
            ->andReturn($templateMock);

        $this->domainService->shouldReceive('getAllTemplatePaths')
            ->once()
            ->andReturn([]);

        $this->templateFinder->shouldReceive('locate')
            ->once()
            ->with('woocommerce/single-product.php')
            ->andReturn([]);

        $result = $this->woocommerce->template($templatePath);

        expect($result)->toBe($templatePath);
    });

    test('returns template path for woocommerce status screen', function () {
        $templatePath = '/plugin/templates/single-product.php';
        $themeTemplatePath = '/theme/woocommerce/single-product.php';

        $this->domainService->shouldReceive('isWooCommerceStatusScreen')
            ->once()
            ->andReturn(true);

        $this->domainService->shouldReceive('getWooCommerceTemplatePath')
            ->once()
            ->andReturn('woocommerce/');

        $templateMock = Mockery::mock();
        $templateMock->shouldReceive('getRelativePath')
            ->with([])
            ->andReturn('single-product.php');

        $this->domainService->shouldReceive('createTemplate')
            ->with($templatePath)
            ->andReturn($templateMock);

        $this->domainService->shouldReceive('getAllTemplatePaths')
            ->once()
            ->andReturn([]);

        $this->templateFinder->shouldReceive('locate')
            ->once()
            ->with('woocommerce/single-product.php')
            ->andReturn([$themeTemplatePath]);

        $this->adapter->shouldReceive('locateTemplate')
            ->once()
            ->with([$themeTemplatePath])
            ->andReturn($themeTemplatePath);

        $result = $this->woocommerce->template($templatePath);

        expect($result)->toBe($themeTemplatePath);
    });

    test('returns original template when view does not exist', function () {
        $templatePath = '/plugin/templates/single-product.php';
        $themeTemplatePath = '/theme/woocommerce/single-product.blade.php';
        $viewName = 'woocommerce.single-product';

        $this->domainService->shouldReceive('isWooCommerceStatusScreen')
            ->once()
            ->andReturn(false);

        $this->domainService->shouldReceive('getWooCommerceTemplatePath')
            ->once()
            ->andReturn('woocommerce/');

        $templateMock = Mockery::mock();
        $templateMock->shouldReceive('getRelativePath')
            ->with([])
            ->andReturn('single-product.php');

        $this->domainService->shouldReceive('createTemplate')
            ->with($templatePath)
            ->andReturn($templateMock);

        $this->domainService->shouldReceive('getAllTemplatePaths')
            ->once()
            ->andReturn([]);

        $this->templateFinder->shouldReceive('locate')
            ->once()
            ->with('woocommerce/single-product.php')
            ->andReturn([$themeTemplatePath]);

        $this->adapter->shouldReceive('locateTemplate')
            ->once()
            ->with([$themeTemplatePath])
            ->andReturn($themeTemplatePath);

        $this->templateFinder->shouldReceive('getViewNameFromPath')
            ->once()
            ->with($themeTemplatePath)
            ->andReturn($viewName);

        $this->viewFactory->shouldReceive('exists')
            ->once()
            ->with($viewName)
            ->andReturn(false);

        $result = $this->woocommerce->template($templatePath);

        expect($result)->toBe($themeTemplatePath);
    });
});