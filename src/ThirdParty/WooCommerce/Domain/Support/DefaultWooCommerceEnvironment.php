<?php

declare(strict_types=1);

namespace Pollora\ThirdParty\WooCommerce\Domain\Support;

use Pollora\ThirdParty\WooCommerce\Domain\Contracts\WooCommerceEnvironmentInterface;

/**
 * The environment of a WooCommerceService built without one: no child theme,
 * and WooCommerce's default override folder.
 */
final readonly class DefaultWooCommerceEnvironment implements WooCommerceEnvironmentInterface
{
    public function isChildTheme(): bool
    {
        return false;
    }

    public function getTemplateDirectory(): string
    {
        return '';
    }

    public function getWooCommerceTemplatePath(): string
    {
        return 'woocommerce/';
    }
}
