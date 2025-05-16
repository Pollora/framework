<?php

declare(strict_types=1);

namespace Pollora\TemplateHierarchy\Infrastructure\Resolvers;

use Pollora\TemplateHierarchy\Domain\Models\TemplateCandidate;
use Pollora\TemplateHierarchy\Infrastructure\Services\AbstractTemplateResolver;

/**
 * Resolver for WooCommerce templates.
 */
class WooCommerceTemplateResolver extends AbstractTemplateResolver
{
    /**
     * The WooCommerce template type for this resolver.
     */
    private string $type;

    /**
     * Create a new WooCommerce template resolver.
     */
    public function __construct(string $type)
    {
        $this->type = $type;
        $this->origin = 'woocommerce';
    }

    /**
     * Check if this resolver applies to the current request.
     */
    public function applies(): bool
    {
        return match ($this->type) {
            'product_category' => function_exists('is_product_category') && is_product_category(),
            'product_tag' => function_exists('is_product_tag') && is_product_tag(),
            'product_taxonomy' => function_exists('is_product_taxonomy') && is_product_taxonomy()
                && ! is_product_category() && ! is_product_tag(),
            'shop' => function_exists('is_shop') && is_shop(),
            'product' => function_exists('is_product') && is_product(),
            'cart' => function_exists('is_cart') && is_cart(),
            'checkout' => function_exists('is_checkout') && is_checkout(),
            'account' => function_exists('is_account_page') && is_account_page(),
            default => false,
        };
    }

    /**
     * Get template candidates for this resolver.
     *
     * @return TemplateCandidate[]
     */
    public function getCandidates(): array
    {
        $templates = $this->getTemplatesForType($this->type);
        $candidates = [];

        foreach ($templates as $template) {
            // Create both PHP and Blade candidates
            $candidates = array_merge(
                $candidates,
                $this->createPhpAndBladeCandidates($template)
            );
        }

        return $candidates;
    }

    /**
     * Get templates for a specific WooCommerce template type.
     *
     * @param  string  $type  Template type
     * @return string[] Array of templates
     */
    private function getTemplatesForType(string $type): array
    {
        return match ($type) {
            'product_category' => $this->productCategoryTemplates(),
            'product_tag' => $this->productTagTemplates(),
            'product_taxonomy' => $this->productTaxonomyTemplates(),
            'shop' => $this->shopTemplates(),
            'product' => $this->productTemplates(),
            'cart' => ['woocommerce/cart.php'],
            'checkout' => $this->checkoutTemplates(),
            'account' => $this->accountTemplates(),
            default => [],
        };
    }

    /**
     * Get WooCommerce product category templates.
     *
     * @return string[]
     */
    private function productCategoryTemplates(): array
    {
        $term = $this->getQueriedObject();
        $templates = [];

        if ($term && isset($term->slug)) {
            // Current category template
            $templates[] = "woocommerce/taxonomy-product_cat-{$term->slug}.php";

            // Try parent category templates if available
            if (isset($term->parent) && $term->parent) {
                $parent = get_term($term->parent, 'product_cat');
                if ($parent && ! is_wp_error($parent)) {
                    $templates[] = "woocommerce/taxonomy-product_cat-{$parent->slug}.php";
                }
            }
        }

        // Generic category template
        $templates[] = 'woocommerce/taxonomy-product_cat.php';

        // Fall back to product archive
        $templates[] = 'woocommerce/archive-product.php';

        return $templates;
    }

    /**
     * Get WooCommerce product tag templates.
     *
     * @return string[]
     */
    private function productTagTemplates(): array
    {
        $term = $this->getQueriedObject();
        $templates = [];

        if ($term && isset($term->slug)) {
            $templates[] = "woocommerce/taxonomy-product_tag-{$term->slug}.php";
        }

        $templates[] = 'woocommerce/taxonomy-product_tag.php';
        $templates[] = 'woocommerce/archive-product.php';

        return $templates;
    }

    /**
     * Get WooCommerce product taxonomy templates.
     *
     * @return string[]
     */
    private function productTaxonomyTemplates(): array
    {
        $term = $this->getQueriedObject();
        $templates = [];

        if ($term && isset($term->taxonomy) && isset($term->slug)) {
            $taxonomy = $term->taxonomy;
            $templates[] = "woocommerce/taxonomy-{$taxonomy}-{$term->slug}.php";
            $templates[] = "woocommerce/taxonomy-{$taxonomy}.php";
        }

        $templates[] = 'woocommerce/archive-product.php';

        return $templates;
    }

    /**
     * Get WooCommerce shop page templates.
     *
     * @return string[]
     */
    private function shopTemplates(): array
    {
        $templates = [];

        // Shop page might have a custom template
        $shopPageId = function_exists('wc_get_page_id') ? wc_get_page_id('shop') : 0;
        if ($shopPageId > 0) {
            $shop_template = get_post_meta($shopPageId, '_wp_page_template', true);
            if ($shop_template && $shop_template !== 'default') {
                $templates[] = $shop_template;
            }

            // Try page-{slug}.php
            $shop_page = get_post($shopPageId);
            if ($shop_page) {
                $templates[] = "woocommerce/page-{$shop_page->post_name}.php";
            }
        }

        $templates[] = 'woocommerce/archive-product.php';

        return $templates;
    }

    /**
     * Get WooCommerce product templates.
     *
     * @return string[]
     */
    private function productTemplates(): array
    {
        $product = $this->getQueriedObject();
        $templates = [];

        if ($product) {
            $productType = function_exists('wc_get_product') ? wc_get_product($product->ID) : null;

            if ($productType && method_exists($productType, 'get_type')) {
                $productSubtype = $productType->get_type();

                // Template specific to product slug
                $templates[] = "woocommerce/single-product-{$product->post_name}.php";

                // Template specific to product type (simple, variable, etc)
                $templates[] = "woocommerce/single-product-{$productSubtype}.php";
            }

            // Custom template assigned to the product
            $wc_template = get_post_meta($product->ID, '_wp_page_template', true);
            if ($wc_template && $wc_template !== 'default') {
                array_unshift($templates, $wc_template);
            }
        }

        // Standard WooCommerce product template
        $templates[] = 'woocommerce/single-product.php';

        return $templates;
    }

    /**
     * Get WooCommerce checkout templates.
     *
     * @return string[]
     */
    private function checkoutTemplates(): array
    {
        $templates = [];

        // Check if we're on a specific checkout endpoint
        if (function_exists('is_wc_endpoint_url') && function_exists('WC') && is_wc_endpoint_url()) {
            $endpoint = WC()->query->get_current_endpoint();
            if ($endpoint) {
                $templates[] = "woocommerce/checkout-{$endpoint}.php";
            }
        }

        // Thank you page
        if (function_exists('is_wc_endpoint_url') && is_wc_endpoint_url('order-received')) {
            $templates[] = 'woocommerce/checkout-thankyou.php';
        }

        // Standard checkout
        $templates[] = 'woocommerce/checkout.php';

        return $templates;
    }

    /**
     * Get WooCommerce account templates.
     *
     * @return string[]
     */
    private function accountTemplates(): array
    {
        $templates = [];

        // Check if we're on a specific account endpoint
        if (function_exists('is_wc_endpoint_url') && function_exists('WC') && is_wc_endpoint_url()) {
            $endpoint = WC()->query->get_current_endpoint();
            if ($endpoint) {
                $templates[] = "woocommerce/myaccount-{$endpoint}.php";
            }
        }

        // Login form
        if (function_exists('is_user_logged_in') && ! is_user_logged_in()) {
            $templates[] = 'woocommerce/myaccount-login.php';
        }

        // Standard account
        $templates[] = 'woocommerce/myaccount.php';

        return $templates;
    }
}
