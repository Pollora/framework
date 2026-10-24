<?php

declare(strict_types=1);

namespace Pollora\ThirdParty\WooCommerce\Domain\Contracts;

/**
 * What the WooCommerce domain needs to know about the active theme and plugin.
 */
interface WooCommerceEnvironmentInterface
{
    /**
     * Whether the active theme is a child theme.
     */
    public function isChildTheme(): bool;

    /**
     * The directory of the parent theme, or of the active theme when it has no parent.
     */
    public function getTemplateDirectory(): string;

    /**
     * The folder themes override WooCommerce templates in, usually 'woocommerce/'.
     */
    public function getWooCommerceTemplatePath(): string;
}
