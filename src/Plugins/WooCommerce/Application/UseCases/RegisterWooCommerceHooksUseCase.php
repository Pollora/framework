<?php

declare(strict_types=1);

namespace Pollora\Plugins\WooCommerce\Application\UseCases;

use Pollora\Hook\Infrastructure\Services\Action;
use Pollora\Hook\Infrastructure\Services\Filter;
use Pollora\Plugins\WooCommerce\Domain\Contracts\TemplateResolverInterface;
use Pollora\Plugins\WooCommerce\Domain\Contracts\WooCommerceIntegrationInterface;

/**
 * Use case for registering WooCommerce hooks and filters.
 *
 * This class orchestrates the registration of all WooCommerce-related
 * WordPress hooks and filters through the application layer.
 */
class RegisterWooCommerceHooksUseCase
{
    public function __construct(
        private readonly Action $action,
        private readonly Filter $filter,
        private readonly WooCommerceIntegrationInterface $woocommerceIntegration,
        private readonly TemplateResolverInterface $templateResolver
    ) {}

    /**
     * Execute the use case to register all WooCommerce hooks.
     */
    public function execute(): void
    {
        $this->registerPluginsLoadedAction();
    }

    /**
     * Register the plugins_loaded action that initializes WooCommerce integration.
     */
    private function registerPluginsLoadedAction(): void
    {
        $this->action->add('plugins_loaded', function (): void {
            if (defined('WC_ABSPATH')) {
                $this->registerTemplateFilters();
                $this->registerSetupActions();
            }
        });
    }

    /**
     * Register WooCommerce-specific template filters.
     */
    private function registerTemplateFilters(): void
    {
        // Hook into WooCommerce's template loader files filter
        $this->filter->add(
            'woocommerce_template_loader_files',
            [$this->templateResolver, 'extendTemplateLoaderFiles'],
            10,
            2
        );

        // Hook into various WooCommerce template filters
        $this->filter->add(
            'woocommerce_locate_template',
            [$this->woocommerceIntegration, 'template'],
            10,
            2
        );

        $this->filter->add(
            'woocommerce_locate_core_template',
            [$this->woocommerceIntegration, 'template'],
            10,
            2
        );

        $this->filter->add(
            'wc_get_template_part',
            [$this->woocommerceIntegration, 'template']
        );

        $this->filter->add(
            'wc_get_template',
            [$this->woocommerceIntegration, 'template'],
            1000
        );

        $this->filter->add(
            'comments_template',
            [$this->woocommerceIntegration, 'reviewsTemplate'],
            11
        );
    }

    /**
     * Register WooCommerce setup actions.
     */
    private function registerSetupActions(): void
    {
        // Load template hooks
        $this->woocommerceIntegration->loadThemeTemplateHooks();

        // Add theme support
        if (function_exists('doing_action') && doing_action('after_setup_theme')) {
            $this->woocommerceIntegration->addThemeSupport();
        } else {
            $this->action->add(
                'after_setup_theme',
                [$this->woocommerceIntegration, 'addThemeSupport']
            );
        }
    }
}