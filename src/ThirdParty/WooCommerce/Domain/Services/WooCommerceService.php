<?php

declare(strict_types=1);

namespace Pollora\ThirdParty\WooCommerce\Domain\Services;

use Pollora\ThirdParty\WooCommerce\Domain\Models\Template;

/**
 * Domain service for WooCommerce business logic.
 *
 * This service contains pure domain logic for WooCommerce
 * integration without external dependencies.
 */
class WooCommerceService
{
    /**
     * Get default WooCommerce template paths.
     *
     * @return array List of default template paths
     */
    public function getDefaultTemplatePaths(): array
    {
        $paths = [];

        // Add WooCommerce plugin templates path if defined
        if (defined('WC_ABSPATH')) {
            $paths[] = WC_ABSPATH.'templates/';
        }

        return $paths;
    }

    /**
     * Get theme template paths for WooCommerce.
     *
     * @return array List of theme template paths
     */
    public function getThemeTemplatePaths(): array
    {
        $paths = [];

        // Add parent theme templates in woocommerce/ subfolder if child theme is active
        if (function_exists('is_child_theme') && is_child_theme() && function_exists('get_template_directory')) {
            $templatePath = 'woocommerce/';
            if (function_exists('WC') && WC() && method_exists(WC(), 'template_path')) {
                $templatePath = WC()->template_path();
            }
            $paths[] = get_template_directory().'/'.$templatePath;
        }

        return $paths;
    }

    /**
     * Check if current screen matches WooCommerce status screen.
     *
     * @param  bool  $isAdmin  Whether currently in admin area
     * @param  bool  $isDoingAjax  Whether currently doing AJAX
     * @param  object|null  $currentScreen  Current screen object
     * @return bool True if on WooCommerce status screen
     */
    public function isWooCommerceStatusScreen(bool $isAdmin, bool $isDoingAjax, ?object $currentScreen): bool
    {
        return $isAdmin
            && ! $isDoingAjax
            && $currentScreen
            && isset($currentScreen->id)
            && $currentScreen->id === 'woocommerce_page_wc-status';
    }

    /**
     * Get WooCommerce template path prefix.
     *
     * @return string The template path prefix (usually 'woocommerce/')
     */
    public function getWooCommerceTemplatePath(): string
    {
        if (function_exists('WC') && WC() && method_exists(WC(), 'template_path')) {
            return WC()->template_path();
        }

        return 'woocommerce/';
    }

    /**
     * Get all template paths (default + theme).
     *
     * @return array Complete list of template paths
     */
    public function getAllTemplatePaths(): array
    {
        return array_merge(
            $this->getDefaultTemplatePaths(),
            $this->getThemeTemplatePaths()
        );
    }

    /**
     * Create a Template instance with WooCommerce context.
     *
     * @param  string  $templatePath  The template file path
     * @return Template The template instance
     */
    public function createTemplate(string $templatePath): Template
    {
        return Template::fromPath($templatePath);
    }

    /**
     * Convert PHP template names to Blade equivalents in a list.
     *
     * @param  array  $templates  List of template names
     * @return array List with Blade template variants added
     */
    public function addBladeVariants(array $templates): array
    {
        $bladeTemplates = [];

        foreach ($templates as $template) {
            $templateObj = $this->createTemplate($template);
            $bladeTemplate = $templateObj->toBladeTemplate();

            if ($bladeTemplate->isBladeTemplate && $bladeTemplate->path !== $templateObj->path) {
                $bladeTemplates[] = $bladeTemplate->path;
            }
        }

        return array_merge($bladeTemplates, $templates);
    }
}
