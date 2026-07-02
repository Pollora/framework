<?php

declare(strict_types=1);

namespace Pollora\ThirdParty\WooCommerce\Infrastructure\Services;

use Illuminate\Contracts\View\Factory as ViewFactory;
use Pollora\ThirdParty\WooCommerce\Domain\Contracts\ComingSoonHandlerInterface;

/**
 * Handles WooCommerce Coming Soon page rendering with Blade templates.
 *
 * This handler replaces WooCommerce's default Coming Soon template
 * (which bypasses the theme's Blade rendering pipeline) with a proper
 * Blade template when one exists in the active theme.
 *
 * WooCommerce's ComingSoonRequestHandler hooks into template_include
 * and renders its own block-based template, calling exit() for classic
 * themes. This handler intercepts that behavior by removing WC's hook
 * and providing a Blade-compatible alternative.
 */
class ComingSoonHandler implements ComingSoonHandlerInterface
{
    /**
     * Blade view name for the coming soon page.
     */
    private const VIEW_NAME = 'woocommerce.coming-soon';

    /**
     * Blade view name for the store-only coming soon page.
     */
    private const STORE_ONLY_VIEW_NAME = 'woocommerce.coming-soon-store-only';

    public function __construct(
        private readonly ViewFactory $viewFactory
    ) {}

    /**
     * {@inheritDoc}
     */
    public function handleTemplateInclude(string $template): string
    {
        if (! $this->shouldShowComingSoon()) {
            return $template;
        }

        $viewName = $this->resolveViewName();

        if ($viewName === null) {
            return $template;
        }

        // Set cache header matching WooCommerce's behavior
        if (! headers_sent()) {
            header('Cache-Control: max-age=60');
        }

        return $this->getTemplatePathForView($viewName) ?? $template;
    }

    /**
     * Determine if the coming soon page should be shown for the current request.
     */
    private function shouldShowComingSoon(): bool
    {
        if (get_option('woocommerce_coming_soon') !== 'yes') {
            return false;
        }

        // Don't show to users who can manage WooCommerce
        if (function_exists('current_user_can') && current_user_can('manage_woocommerce')) {
            return false;
        }

        // Check private link / share key access
        if ($this->hasValidShareKeyAccess()) {
            return false;
        }

        $storeOnly = get_option('woocommerce_store_pages_only') === 'yes';

        if ($storeOnly) {
            // Only show on store pages (404 pages excluded)
            if (function_exists('is_404') && is_404()) {
                return false;
            }

            return $this->isStorePage();
        }

        // Whole site coming soon
        return true;
    }

    /**
     * Check if the current request has valid share key access.
     */
    private function hasValidShareKeyAccess(): bool
    {
        if (get_option('woocommerce_private_link') !== 'yes') {
            return false;
        }

        $shareKey = get_option('woocommerce_share_key', '');
        if ($shareKey === '' || $shareKey === '0') {
            return false;
        }

        // Check cookie set by WooCommerce's share link feature
        return isset($_COOKIE['woocommerce_share_key']) && $_COOKIE['woocommerce_share_key'] === $shareKey;
    }

    /**
     * Determine if the current page is a WooCommerce store page.
     */
    private function isStorePage(): bool
    {
        if (! function_exists('is_woocommerce')) {
            return false;
        }

        if (is_woocommerce()
            || (function_exists('is_cart') && is_cart())
            || (function_exists('is_checkout') && is_checkout())
            || (function_exists('is_account_page') && is_account_page())) {
            return true;
        }

        // Check shop page
        if (function_exists('is_shop') && is_shop()) {
            return true;
        }

        if (! function_exists('wc_terms_and_conditions_page_id')) {
            return false;
        }

        $termsPageId = wc_terms_and_conditions_page_id();
        if ($termsPageId > 0 && is_page($termsPageId)) {
            return true;
        }

        return false;
    }

    /**
     * Resolve which Blade view to use for coming soon.
     */
    private function resolveViewName(): ?string
    {
        $storeOnly = get_option('woocommerce_store_pages_only') === 'yes';

        // Try store-only variant first if applicable
        /** @phpstan-ignore method.impossibleType */
        if ($storeOnly && $this->viewFactory->exists(self::STORE_ONLY_VIEW_NAME)) {
            return self::STORE_ONLY_VIEW_NAME;
        }

        // Fall back to generic coming soon template
        /** @phpstan-ignore method.impossibleType */
        if ($this->viewFactory->exists(self::VIEW_NAME)) {
            return self::VIEW_NAME;
        }

        return null;
    }

    /**
     * Get the file path for a Blade view so FrontendController can render it.
     */
    private function getTemplatePathForView(string $viewName): ?string
    {
        try {
            /** @phpstan-ignore method.notFound */
            return $this->viewFactory->make($viewName)->getPath();
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    /**
     * Remove WooCommerce's native ComingSoonRequestHandler from template_include.
     *
     * WC's handler calls exit() for classic themes, which would prevent
     * the Blade rendering pipeline from working.
     */
    public static function removeWooCommerceHandler(): void
    {
        global $wp_filter;

        if (! isset($wp_filter['template_include'])) {
            return;
        }

        foreach ($wp_filter['template_include']->callbacks as $priority => $callbacks) {
            foreach ($callbacks as $id => $callback) {
                if (! is_array($callback['function'] ?? null)) {
                    continue;
                }

                $fn = $callback['function'];
                if (
                    is_object($fn[0])
                    && str_ends_with($fn[0]::class, 'ComingSoonRequestHandler')
                    && ($fn[1] ?? '') === 'handle_template_include'
                ) {
                    remove_filter('template_include', $fn, $priority);

                    return;
                }
            }
        }
    }
}
