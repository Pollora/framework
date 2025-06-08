<?php

declare(strict_types=1);

namespace Pollora\Plugins\WooCommerce;

use Illuminate\Contracts\Container\Container as ContainerContract;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Support\Str;
use Pollora\View\Domain\Contracts\TemplateFinderInterface;

/**
 * WooCommerce integration for Pollora framework.
 *
 * This class provides seamless integration between WooCommerce templates
 * and Pollora's Blade template system, allowing developers to use Blade
 * templates for WooCommerce views while maintaining full compatibility
 * with WordPress and WooCommerce functionality.
 */
class WooCommerce
{
    /**
     * The application container.
     */
    protected ContainerContract $app;

    /**
     * The template finder for locating template files.
     */
    protected TemplateFinderInterface $templateFinder;

    /**
     * The view factory for creating views.
     */
    protected ViewFactory $viewFactory;

    /**
     * Create a new WooCommerce instance.
     *
     * @param  ContainerContract  $app  The application container
     * @param  TemplateFinderInterface  $templateFinder  Template finder service
     * @param  ViewFactory  $viewFactory  View factory for creating views
     */
    public function __construct(
        ContainerContract $app,
        TemplateFinderInterface $templateFinder,
        ViewFactory $viewFactory
    ) {
        $this->app = $app;
        $this->templateFinder = $templateFinder;
        $this->viewFactory = $viewFactory;
    }

    /**
     * Load theme template hook overrides file if available.
     *
     * This allows themes to override WooCommerce template hooks by placing
     * a wc-template-hooks.php file in their theme directory.
     */
    public function loadThemeTemplateHooks(): void
    {
        if (function_exists('locate_template')) {
            locate_template('wc-template-hooks.php', true, true);
        }
    }

    /**
     * Declare WooCommerce theme support.
     *
     * This enables WooCommerce functionality in the current theme,
     * allowing it to properly display WooCommerce pages and content.
     */
    public function addThemeSupport(): void
    {
        if (function_exists('add_theme_support')) {
            add_theme_support('woocommerce');
        }
    }

    /**
     * Support Blade templates for WooCommerce comments/reviews.
     *
     * This method filters the comments template to use Blade templates
     * when available for WooCommerce product reviews.
     *
     * @param  string  $template  The template file path
     * @return string The filtered template path
     */
    public function reviewsTemplate(string $template): string
    {
        if (! $this->isWooCommerceTemplate($template)) {
            return $template;
        }

        return $this->template($template);
    }

    /**
     * Filter a template path, taking into account theme templates and creating
     * Blade loaders as needed.
     *
     * This is the core method that handles template resolution for WooCommerce.
     * It checks if a Blade template exists and creates a loader file if needed.
     *
     * @param  string  $template  The template file path
     * @param  string  $templateName  Optional template name for additional context
     * @return string The filtered template path
     */
    public function template(string $template, string $templateName = ''): string
    {
        // Locate any matching template within the theme
        $themeTemplate = $this->locateThemeTemplate($templateName ?: $template);

        if (! $themeTemplate) {
            return $template;
        }

        // Return filename for WooCommerce status screen
        if ($this->isWooCommerceStatusScreen()) {
            return $themeTemplate;
        }

        // Include directly unless it's a Blade file
        if (! Str::endsWith($themeTemplate, '.blade.php')) {
            return $themeTemplate;
        }

        // We have a Blade template, get the view name and create a loader
        $viewName = $this->getViewNameFromTemplate($themeTemplate);

        if (! $viewName || ! $this->viewFactory->exists($viewName)) {
            return $themeTemplate;
        }

        // Create and return the loader file path
        return $this->viewFactory->make($viewName)->makeLoader();
    }

    /**
     * Get view name from template file path.
     *
     * This converts a template file path to a view name that can be used
     * with Laravel's view factory.
     *
     * @param  string  $templatePath  The template file path
     * @return string|null The view name or null if it cannot be determined
     */
    protected function getViewNameFromTemplate(string $templatePath): ?string
    {
        $realPath = realpath($templatePath);
        if (! $realPath) {
            return null;
        }

        return $this->templateFinder->getViewNameFromPath($realPath);
    }

    /**
     * Check if we're on the WooCommerce status screen.
     *
     * The WooCommerce status screen needs to display actual template paths
     * for debugging purposes, so we return the real template path instead
     * of a loader file.
     *
     * @return bool True if on WooCommerce status screen
     */
    protected function isWooCommerceStatusScreen(): bool
    {
        return is_admin()
            && ! wp_doing_ajax()
            && function_exists('get_current_screen')
            && get_current_screen()
            && get_current_screen()->id === 'woocommerce_page_wc-status';
    }

    /**
     * Check if template is a WooCommerce template.
     *
     * This determines whether a template path belongs to WooCommerce
     * by checking if it can be made relative to WooCommerce paths.
     *
     * @param  string  $template  The template file path
     * @return bool True if it's a WooCommerce template
     */
    protected function isWooCommerceTemplate(string $template): bool
    {
        return $this->relativeTemplatePath($template) !== $template;
    }

    /**
     * Return the theme relative template path.
     *
     * This method strips WooCommerce plugin paths from the template path
     * to get a path relative to the theme directory.
     *
     * @param  string  $template  The template file path
     * @return string The relative template path
     */
    protected function relativeTemplatePath(string $template): string
    {
        $defaultPaths = [];

        // Add WooCommerce plugin templates path if defined
        if (defined('WC_ABSPATH')) {
            $defaultPaths[] = WC_ABSPATH . 'templates/';
        }

        // Add parent theme templates in woocommerce/ subfolder if child theme is active
        if (function_exists('is_child_theme') && is_child_theme() && function_exists('get_template_directory')) {
            $templatePath = '';
            if (function_exists('WC') && WC() && method_exists(WC(), 'template_path')) {
                $templatePath = WC()->template_path();
            } else {
                $templatePath = 'woocommerce/';
            }
            $defaultPaths[] = get_template_directory() . '/' . $templatePath;
        }

        // Allow filtering of template paths
        $defaultPaths = apply_filters('pollora/woocommerce/template_paths', $defaultPaths);

        return str_replace($defaultPaths, '', $template);
    }

    /**
     * Locate the theme's WooCommerce Blade template when available.
     *
     * This method searches for WooCommerce templates in the theme directory,
     * prioritizing Blade templates over PHP templates.
     *
     * @param  string  $template  The template name to locate
     * @return string The absolute path to the template, or empty string if not found
     */
    protected function locateThemeTemplate(string $template): string
    {
        // Get WooCommerce template path (usually 'woocommerce/')
        $wcTemplatePath = '';
        if (function_exists('WC') && WC() && method_exists(WC(), 'template_path')) {
            $wcTemplatePath = WC()->template_path();
        } else {
            $wcTemplatePath = 'woocommerce/';
        }

        // Build the theme template path: woocommerce/single-product.php
        $themeTemplate = $wcTemplatePath . $this->relativeTemplatePath($template);

        // Use the template finder to locate the template
        $foundTemplates = $this->templateFinder->locate($themeTemplate);

        // Return the first found template (Blade templates are prioritized in the finder)
        if (! empty($foundTemplates) && function_exists('locate_template')) {
            return locate_template($foundTemplates);
        }

        return '';
    }
}
