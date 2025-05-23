<?php

declare(strict_types=1);

namespace Pollora\Plugins\WooCommerce;

use Illuminate\Support\ServiceProvider;
use Pollora\Plugins\WooCommerce\View\WooCommerceView;
use Pollora\Support\Facades\Action;

class WooCommerceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(WooCommerceView::class, WooCommerceView::class);
        Action::add('plugins_loaded', function (): void {
            if (defined('WC_ABSPATH')) {
                $this->bindFilters();
            }
        });
    }

    public function bindFilters(): void
    {
        $wp_view = $this->app->make(WooCommerceView::class);

        // add_filter('template_include', [$wp_view, 'templateInclude'], 11);
        add_filter('woocommerce_locate_template', [$wp_view, 'template']);
        add_filter('wc_get_template_part', [$wp_view, 'template']);
        add_filter('comments_template', [$wp_view, 'reviewsTemplate'], 11);
        add_filter('wc_get_template', [$wp_view, 'template'], 1000);
    }
}
