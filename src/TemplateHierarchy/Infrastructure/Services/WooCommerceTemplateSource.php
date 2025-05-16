<?php

declare(strict_types=1);

namespace Pollora\TemplateHierarchy\Infrastructure\Services;

use Pollora\TemplateHierarchy\Domain\Contracts\TemplateResolverInterface;
use Pollora\TemplateHierarchy\Infrastructure\Resolvers\WooCommerceTemplateResolver;

/**
 * WooCommerce template source that provides WooCommerce-specific templates.
 */
class WooCommerceTemplateSource extends AbstractTemplateSource
{
    /**
     * Create a new WooCommerce template source.
     */
    public function __construct()
    {
        $this->name = 'woocommerce';
        $this->priority = 10; // Higher priority than WordPress source
    }

    /**
     * Get the template resolvers for WooCommerce templates.
     *
     * @return TemplateResolverInterface[]
     */
    public function getResolvers(): array
    {
        // Early bail if WooCommerce is not active
        if (! function_exists('is_woocommerce')) {
            return [];
        }

        $resolvers = [];

        // Add resolvers for each WooCommerce template type
        $resolvers[] = new WooCommerceTemplateResolver('product_category');
        $resolvers[] = new WooCommerceTemplateResolver('product_tag');
        $resolvers[] = new WooCommerceTemplateResolver('product_taxonomy');
        $resolvers[] = new WooCommerceTemplateResolver('shop');
        $resolvers[] = new WooCommerceTemplateResolver('product');
        $resolvers[] = new WooCommerceTemplateResolver('cart');
        $resolvers[] = new WooCommerceTemplateResolver('checkout');
        $resolvers[] = new WooCommerceTemplateResolver('account');

        return $resolvers;
    }
}
