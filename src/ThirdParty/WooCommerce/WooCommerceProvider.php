<?php

declare(strict_types=1);

namespace Pollora\ThirdParty\WooCommerce;

use Illuminate\Support\ServiceProvider;
use Pollora\Hook\Infrastructure\Services\Action;
use Pollora\Hook\Infrastructure\Services\Filter;
use Pollora\ThirdParty\WooCommerce\View\WooCommerceView;

class WooCommerceProvider extends ServiceProvider
{
    protected Action $action;

    protected Filter $filter;

    public function register(): void
    {
        /** @var Action $action * */
        $this->action = $this->app->make(Action::class);
        /** @var Filter $filter */
        $this->filter = $this->app->make(Filter::class);

        $this->app->singleton(WooCommerceView::class, WooCommerceView::class);
    }

    public function boot()
    {
        $this->action->add('plugins_loaded', function (): void {
            if (defined('WC_ABSPATH')) {
                $this->bindFilters();
            }
        });
    }

    public function bindFilters(): void
    {
        $wp_view = $this->app->make(WooCommerceView::class);
        $this->filter->add('woocommerce_locate_template', [$wp_view, 'template']);
        $this->filter->add('wc_get_template_part', [$wp_view, 'template']);
        $this->filter->add('comments_template', [$wp_view, 'reviewsTemplate'], 11);
        $this->filter->add('wc_get_template', [$wp_view, 'template'], 1000);
    }
}
