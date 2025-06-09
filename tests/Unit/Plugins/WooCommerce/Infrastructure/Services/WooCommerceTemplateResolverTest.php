<?php

declare(strict_types=1);

use Illuminate\Contracts\View\Factory as ViewFactory;
use Pollora\Plugins\WooCommerce\Domain\Services\WooCommerceService;
use Pollora\Plugins\WooCommerce\Infrastructure\Services\WooCommerceTemplateResolver;
use Pollora\View\Domain\Contracts\TemplateFinderInterface;

describe('WooCommerceTemplateResolver', function () {
    beforeEach(function () {
        setupWordPressMocks();

        $this->templateFinder = Mockery::mock(TemplateFinderInterface::class);
        $this->viewFactory = Mockery::mock(ViewFactory::class);
        $this->domainService = Mockery::mock(WooCommerceService::class);

        $this->resolver = new WooCommerceTemplateResolver(
            $this->templateFinder,
            $this->viewFactory,
            $this->domainService
        );
    });

    afterEach(function () {
        resetWordPressMocks();
        Mockery::close();
    });

    test('returns original templates when no default file provided', function () {
        $templates = ['single-product.php', 'archive-product.php'];

        $result = $this->resolver->extendTemplateLoaderFiles($templates, '');

        expect($result)->toBe($templates);
    });

    test('can extend template loader files with blade variants', function () {
        $templates = ['single-product.php', 'archive-product.php'];
        $defaultFile = 'single-product.php';

        $this->domainService->shouldReceive('addBladeVariants')
            ->once()
            ->with($templates)
            ->andReturn(['single-product.blade.php', 'archive-product.blade.php', 'single-product.php', 'archive-product.php']);

        $this->domainService->shouldReceive('createTemplate')
            ->once()
            ->with($defaultFile)
            ->andReturn(Mockery::mock()->shouldReceive('toBladeTemplate')->andReturn(
                Mockery::mock(['isBladeTemplate' => true, 'path' => 'single-product.blade.php'])
            )->getMock());

        $this->domainService->shouldReceive('getWooCommerceTemplatePath')
            ->once()
            ->andReturn('woocommerce/');

        $result = $this->resolver->extendTemplateLoaderFiles($templates, $defaultFile);

        expect($result)->toContain('single-product.blade.php');
        expect($result)->toContain('archive-product.blade.php');
        expect($result)->toContain('views/single-product.blade.php');
        expect($result)->toContain('views/woocommerce/single-product.blade.php');
        expect($result)->toContain('single-product.php');
        expect($result)->toContain('archive-product.php');
    });

    test('handles default file that is already blade template', function () {
        $templates = ['single-product.php'];
        $defaultFile = 'single-product.blade.php';

        $this->domainService->shouldReceive('addBladeVariants')
            ->once()
            ->with($templates)
            ->andReturn(['single-product.blade.php', 'single-product.php']);

        $templateMock = Mockery::mock();
        $templateMock->shouldReceive('toBladeTemplate')->andReturnSelf();
        $templateMock->isBladeTemplate = true;
        $templateMock->path = 'single-product.blade.php';

        $this->domainService->shouldReceive('createTemplate')
            ->once()
            ->with($defaultFile)
            ->andReturn($templateMock);

        $this->domainService->shouldReceive('getWooCommerceTemplatePath')
            ->once()
            ->andReturn('woocommerce/');

        $result = $this->resolver->extendTemplateLoaderFiles($templates, $defaultFile);

        expect($result)->toContain('views/single-product.blade.php');
        expect($result)->toContain('views/woocommerce/single-product.blade.php');
    });

    test('does not add blade versions for non-convertible templates', function () {
        $templates = ['style.css', 'script.js'];
        $defaultFile = 'style.css';

        $this->domainService->shouldReceive('addBladeVariants')
            ->once()
            ->with($templates)
            ->andReturn(['style.css', 'script.js']); // No blade variants added

        $templateMock = Mockery::mock();
        $templateMock->shouldReceive('toBladeTemplate')->andReturnSelf();
        $templateMock->isBladeTemplate = false;
        $templateMock->path = 'style.css';

        $this->domainService->shouldReceive('createTemplate')
            ->once()
            ->with($defaultFile)
            ->andReturn($templateMock);

        $result = $this->resolver->extendTemplateLoaderFiles($templates, $defaultFile);

        expect($result)->toBe(['style.css', 'script.js']);
        expect($result)->not->toContain('views/style.blade.css');
    });

    test('removes duplicate templates from result', function () {
        $templates = ['single-product.php', 'single-product.blade.php'];
        $defaultFile = 'single-product.php';

        $this->domainService->shouldReceive('addBladeVariants')
            ->once()
            ->with($templates)
            ->andReturn(['single-product.blade.php', 'single-product.php', 'single-product.blade.php']); // Contains duplicate

        $this->domainService->shouldReceive('createTemplate')
            ->once()
            ->with($defaultFile)
            ->andReturn(Mockery::mock()->shouldReceive('toBladeTemplate')->andReturn(
                Mockery::mock(['isBladeTemplate' => true, 'path' => 'single-product.blade.php'])
            )->getMock());

        $this->domainService->shouldReceive('getWooCommerceTemplatePath')
            ->once()
            ->andReturn('woocommerce/');

        $result = $this->resolver->extendTemplateLoaderFiles($templates, $defaultFile);

        // Count occurrences of blade template
        $bladeCount = count(array_filter($result, fn ($template) => $template === 'single-product.blade.php'));
        expect($bladeCount)->toBe(1);
    });

    test('prioritizes blade templates by placing them first', function () {
        $templates = ['single-product.php', 'archive-product.php'];
        $defaultFile = 'single-product.php';

        $this->domainService->shouldReceive('addBladeVariants')
            ->once()
            ->with($templates)
            ->andReturn(['single-product.blade.php', 'archive-product.blade.php', 'single-product.php', 'archive-product.php']);

        $this->domainService->shouldReceive('createTemplate')
            ->once()
            ->with($defaultFile)
            ->andReturn(Mockery::mock()->shouldReceive('toBladeTemplate')->andReturn(
                Mockery::mock(['isBladeTemplate' => true, 'path' => 'single-product.blade.php'])
            )->getMock());

        $this->domainService->shouldReceive('getWooCommerceTemplatePath')
            ->once()
            ->andReturn('woocommerce/');

        $result = $this->resolver->extendTemplateLoaderFiles($templates, $defaultFile);

        // First few items should be blade templates
        expect($result[0])->toMatch('/\.blade\.php$/');

        // Original templates should still be present but later
        expect($result)->toContain('single-product.php');
        expect($result)->toContain('archive-product.php');
    });

    test('handles empty templates array', function () {
        $templates = [];
        $defaultFile = 'single-product.php';

        $this->domainService->shouldReceive('addBladeVariants')
            ->once()
            ->with($templates)
            ->andReturn([]);

        $this->domainService->shouldReceive('createTemplate')
            ->once()
            ->with($defaultFile)
            ->andReturn(Mockery::mock()->shouldReceive('toBladeTemplate')->andReturn(
                Mockery::mock(['isBladeTemplate' => true, 'path' => 'single-product.blade.php'])
            )->getMock());

        $this->domainService->shouldReceive('getWooCommerceTemplatePath')
            ->once()
            ->andReturn('woocommerce/');

        $result = $this->resolver->extendTemplateLoaderFiles($templates, $defaultFile);

        expect($result)->toContain('views/single-product.blade.php');
        expect($result)->toContain('views/woocommerce/single-product.blade.php');
    });
});
