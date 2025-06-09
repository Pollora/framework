<?php

declare(strict_types=1);

namespace Pollora\Plugins\WooCommerce\Infrastructure\Adapters;

/**
 * Adapter for WordPress WooCommerce-specific functionality.
 *
 * This class provides an abstraction layer for WordPress and WooCommerce
 * specific functions, making the domain layer more testable and independent.
 */
class WordPressWooCommerceAdapter
{
    /**
     * Locate a template using WordPress locate_template function.
     *
     * @param  string|array  $templates  Template file(s) to search for
     * @param  bool  $load  Whether to load the template if found
     * @param  bool  $requireOnce  Whether to require_once or require
     * @return string The located template path
     */
    public function locateTemplate(string|array $templates, bool $load = false, bool $requireOnce = true): string
    {
        if (function_exists('locate_template')) {
            return locate_template($templates, $load, $requireOnce);
        }

        return '';
    }

    /**
     * Add theme support for WooCommerce.
     *
     * @param  string  $feature  The feature to add support for
     * @param  mixed  $options  Optional feature options
     * @return void|false False on failure, void on success
     */
    public function addThemeSupport(string $feature, mixed $options = null): mixed
    {
        if (function_exists('add_theme_support')) {
            if ($options !== null) {
                return add_theme_support($feature, $options);
            }
            return add_theme_support($feature);
        }

        return false;
    }

    /**
     * Check if current theme is a child theme.
     *
     * @return bool True if child theme, false otherwise
     */
    public function isChildTheme(): bool
    {
        return function_exists('is_child_theme') && is_child_theme();
    }

    /**
     * Get the current stylesheet directory.
     *
     * @return string The stylesheet directory path
     */
    public function getStylesheetDirectory(): string
    {
        return function_exists('get_stylesheet_directory') ? get_stylesheet_directory() : '';
    }

    /**
     * Get the template directory.
     *
     * @return string The template directory path
     */
    public function getTemplateDirectory(): string
    {
        return function_exists('get_template_directory') ? get_template_directory() : '';
    }

    /**
     * Check if we're in WordPress admin area.
     *
     * @return bool True if in admin area
     */
    public function isAdmin(): bool
    {
        return function_exists('is_admin') && is_admin();
    }

    /**
     * Check if WordPress is doing AJAX.
     *
     * @return bool True if doing AJAX
     */
    public function isDoingAjax(): bool
    {
        return function_exists('wp_doing_ajax') && wp_doing_ajax();
    }

    /**
     * Get current WordPress screen.
     *
     * @return \WP_Screen|null Current screen object or null
     */
    public function getCurrentScreen(): ?\WP_Screen
    {
        if (function_exists('get_current_screen')) {
            return get_current_screen();
        }

        return null;
    }

    /**
     * Check if currently performing an action.
     *
     * @param  string  $action  The action to check for
     * @return bool True if performing the action
     */
    public function isDoingAction(string $action): bool
    {
        return function_exists('doing_action') && doing_action($action);
    }

    /**
     * Get WooCommerce template path.
     *
     * @return string The WooCommerce template path
     */
    public function getWooCommerceTemplatePath(): string
    {
        if (function_exists('WC') && WC() && method_exists(WC(), 'template_path')) {
            return WC()->template_path();
        }

        return 'woocommerce/';
    }

    /**
     * Apply WordPress filters.
     *
     * @param  string  $hook  The name of the filter hook
     * @param  mixed  $value  The value to filter
     * @param  mixed  ...$args  Additional arguments to pass to callbacks
     * @return mixed The filtered value
     */
    public function applyFilters(string $hook, mixed $value, mixed ...$args): mixed
    {
        if (function_exists('apply_filters')) {
            return apply_filters($hook, $value, ...$args);
        }

        return $value;
    }

    /**
     * Check if WooCommerce is available and loaded.
     *
     * @return bool True if WooCommerce is available
     */
    public function isWooCommerceAvailable(): bool
    {
        return defined('WC_ABSPATH') && function_exists('WC');
    }
}