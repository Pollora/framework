<?php

declare(strict_types=1);

namespace Pollora\ThirdParty\WooCommerce\Infrastructure\Services;

use Pollora\ThirdParty\WooCommerce\Domain\Contracts\TemplateResolverInterface;
use Pollora\ThirdParty\WooCommerce\Domain\Services\WooCommerceService;

/**
 * Infrastructure implementation of WooCommerce template resolver.
 *
 * This class provides the concrete implementation of template resolution
 * for WooCommerce using Laravel's view system and domain services.
 */
class WooCommerceTemplateResolver implements TemplateResolverInterface
{
    public function __construct(
        private readonly WooCommerceService $domainService
    ) {}

    /**
     * {@inheritDoc}
     */
    public function extendTemplateLoaderFiles(array $templates, string $defaultFile): array
    {
        if ($defaultFile === '' || $defaultFile === '0') {
            return $templates;
        }

        $bladeTemplates = [];

        // Convert existing templates to Blade equivalents using domain service
        $bladeTemplates = array_merge($bladeTemplates, $this->domainService->addBladeVariants($templates));

        // Add Blade version of the default file
        $defaultTemplate = $this->domainService->createTemplate($defaultFile);
        $bladeDefaultTemplate = $defaultTemplate->toBladeTemplate();

        if ($bladeDefaultTemplate->isBladeTemplate && $bladeDefaultTemplate->path !== $defaultTemplate->path) {
            $bladeTemplates[] = 'resources/views/'.$bladeDefaultTemplate->path;

            // Also add the WooCommerce template path version
            $wcPath = $this->domainService->getWooCommerceTemplatePath();
            $bladeTemplates[] = 'resources/views/'.$wcPath.$bladeDefaultTemplate->path;
        }

        // Remove duplicates and merge Blade templates at the beginning for priority
        $bladeTemplates = array_unique($bladeTemplates);

        return array_merge($bladeTemplates, $templates);
    }
}
