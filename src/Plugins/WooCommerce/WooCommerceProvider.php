<?php

declare(strict_types=1);

namespace Pollora\Plugins\WooCommerce;

use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Support\ServiceProvider;
use Pollora\Hook\Infrastructure\Services\Action;
use Pollora\Hook\Infrastructure\Services\Filter;
use Pollora\Plugins\WooCommerce\View\WooCommerceTemplateResolver;
use Pollora\View\Domain\Contracts\TemplateFinderInterface;

/**
 * WooCommerce integration service provider for Pollora framework.
 *
 * This service provider registers WooCommerce-specific services and binds
 * the necessary filters to integrate Blade templates with WooCommerce's
 * template system.
 */
class WooCommerceProvider extends ServiceProvider
{
    /**
     * WordPress action service.
     */
    protected Action $action;

    /**
     * WordPress filter service.
     */
    protected Filter $filter;

    /**
     * Register any application services.
     */
    public function register(): void
    {
        /** @var Action $action */
        $this->action = $this->app->make(Action::class);
        /** @var Filter $filter */
        $this->filter = $this->app->make(Filter::class);

        // Register the main WooCommerce integration class
        $this->app->singleton(WooCommerce::class, function ($app) {
            return new WooCommerce(
                $app,
                $app->make(TemplateFinderInterface::class),
                $app->make(ViewFactory::class)
            );
        });

        // Register the template resolver for extending template loader files
        $this->app->singleton(WooCommerceTemplateResolver::class, function ($app) {
            return new WooCommerceTemplateResolver(
                $app->make(TemplateFinderInterface::class),
                $app->make(ViewFactory::class)
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->action->add('plugins_loaded', function (): void {
            if (defined('WC_ABSPATH')) {
                $this->bindFilters();
                $this->bindSetupAction();
            }
        });
    }

    /**
     * Bind WooCommerce-specific filters.
     *
     * This method hooks into various WooCommerce filters to provide
     * Blade template support throughout the WooCommerce system.
     */
    public function bindFilters(): void
    {
        $woocommerce = $this->app->make(WooCommerce::class);
        $resolver = $this->app->make(WooCommerceTemplateResolver::class);

        // Hook into WooCommerce's template loader files filter
        // This extends the search list with Blade template candidates
        $this->filter->add('woocommerce_template_loader_files', [$resolver, 'extendTemplateLoaderFiles'], 10, 2);

        // Hook into various WooCommerce template filters
        $this->filter->add('woocommerce_locate_template', [$woocommerce, 'template'], 10, 2);
        $this->filter->add('woocommerce_locate_core_template', [$woocommerce, 'template'], 10, 2);
        $this->filter->add('wc_get_template_part', [$woocommerce, 'template']);
        $this->filter->add('wc_get_template', [$woocommerce, 'template'], 1000);
        $this->filter->add('comments_template', [$woocommerce, 'reviewsTemplate'], 11);
    }

    /**
     * Bind WooCommerce setup action.
     *
     * This ensures WooCommerce theme support is added at the right time,
     * either immediately if we're already in the setup phase, or by
     * hooking into the after_setup_theme action.
     */
    public function bindSetupAction(): void
    {
        $woocommerce = $this->app->make(WooCommerce::class);

        // Load template hooks
        $woocommerce->loadThemeTemplateHooks();

        // Add theme support
        if (function_exists('doing_action') && doing_action('after_setup_theme')) {
            $woocommerce->addThemeSupport();
        } else {
            $this->action->add('after_setup_theme', [$woocommerce, 'addThemeSupport']);
        }
    }
}
