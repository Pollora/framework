<?php

declare(strict_types=1);

use Pollora\ThirdParty\WooCommerce\Domain\Contracts\TemplateResolverInterface;
use Pollora\ThirdParty\WooCommerce\Domain\Models\Template;
use Pollora\ThirdParty\WooCommerce\Domain\Services\WooCommerceService;
use Pollora\ThirdParty\WooCommerce\Infrastructure\Services\WooCommerceTemplateResolver;

describe('WooCommerceTemplateResolver', function (): void {
    beforeEach(function (): void {
        $this->domainService = Mockery::mock(WooCommerceService::class);
        $this->resolver = new WooCommerceTemplateResolver($this->domainService);
    });

    it('implements TemplateResolverInterface', function (): void {
        expect($this->resolver)->toBeInstanceOf(TemplateResolverInterface::class);
    });

    it('returns templates unchanged when defaultFile is empty', function (): void {
        $templates = ['single-product.php', 'woocommerce.php'];

        expect($this->resolver->extendTemplateLoaderFiles($templates, ''))->toBe($templates);
    });

    it('prepends blade variants to templates', function (): void {
        $templates = ['single-product.php'];

        $this->domainService->shouldReceive('addBladeVariants')
            ->with($templates)
            ->andReturn(['single-product.blade.php']);

        $defaultTemplate = new Template('archive-product.php', 'archive-product', false);
        $bladeDefault = new Template('archive-product.blade.php', 'archive-product', true);

        $this->domainService->shouldReceive('createTemplate')
            ->with('archive-product.php')
            ->andReturn($defaultTemplate);

        $this->domainService->shouldReceive('getWooCommerceTemplatePath')
            ->andReturn('woocommerce/');

        $result = $this->resolver->extendTemplateLoaderFiles($templates, 'archive-product.php');

        // Blade variants should come first
        expect($result[0])->toBe('single-product.blade.php');
        // Then resource views of the default
        expect($result)->toContain('resources/views/archive-product.blade.php');
        expect($result)->toContain('resources/views/woocommerce/archive-product.blade.php');
        // Original templates at the end
        expect(end($result))->toBe('single-product.php');
    });

    it('does not add blade resource views when default is already blade', function (): void {
        $templates = ['single-product.php'];

        $this->domainService->shouldReceive('addBladeVariants')
            ->with($templates)
            ->andReturn([]);

        // Already a blade template — toBladeTemplate() returns self (same path)
        $defaultTemplate = new Template('product.blade.php', 'product', true);

        $this->domainService->shouldReceive('createTemplate')
            ->with('product.blade.php')
            ->andReturn($defaultTemplate);

        $result = $this->resolver->extendTemplateLoaderFiles($templates, 'product.blade.php');

        // No resources/views paths should be added since blade path === original path
        $resourceViews = array_filter($result, fn ($t): bool => str_starts_with($t, 'resources/views/'));
        expect($resourceViews)->toBeEmpty();
        expect($result)->toContain('single-product.php');
    });
});
