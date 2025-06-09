<?php

declare(strict_types=1);

namespace Pollora\Plugins\WooCommerce\Domain\Contracts;

/**
 * Interface for WooCommerce integration services.
 *
 * This interface defines the contract for implementing WooCommerce
 * integration functionality within the Pollora framework.
 */
interface WooCommerceIntegrationInterface
{
    /**
     * Load theme template hook overrides file if available.
     */
    public function loadThemeTemplateHooks(): void;

    /**
     * Declare WooCommerce theme support.
     */
    public function addThemeSupport(): void;

    /**
     * Support Blade templates for WooCommerce comments/reviews.
     *
     * @param  string  $template  The template file path
     * @return string The filtered template path
     */
    public function reviewsTemplate(string $template): string;

    /**
     * Filter a template path, taking into account theme templates and creating
     * Blade loaders as needed.
     *
     * @param  string  $template  The template file path
     * @param  string  $templateName  Optional template name for additional context
     * @return string The filtered template path
     */
    public function template(string $template, string $templateName = ''): string;
}
