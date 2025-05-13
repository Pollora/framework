<?php

declare(strict_types=1);

namespace Pollora\Plugins\WooCommerce;

use Illuminate\Support\ServiceProvider;
use Pollora\Container\Domain\ServiceLocator;
use Pollora\Hook\Infrastructure\Services\Action;
use Pollora\Hook\Infrastructure\Services\Filter;
use Pollora\Plugins\WooCommerce\View\WooCommerceView;

class WooCommerceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    protected ServiceLocator $locator;

    protected Action $action;

    protected Filter $filter;

    public function register(): void
    {
        /** @var ServiceLocator $locator */
        $this->locator = $this->app->make(ServiceLocator::class);
        /** @var Action $action * */
        $this->action = $this->locator->resolve(Action::class);
        /** @var Filter $filter */
        $this->filter = $this->locator->resolve(Filter::class);

        $this->app->singleton(WooCommerceView::class, WooCommerceView::class);
        $this->action->add('plugins_loaded', function (): void {
            if (defined('WC_ABSPATH')) {
                $this->bindFilters();
            }
        });
    }

    public function bindFilters(): void
    {
        $wp_view = $this->locator->resolve(\Pollora\Plugins\WooCommerce\View\WooCommerceView::class);
        $this->filter->add('woocommerce_locate_template', [$wp_view, 'template']);
        $this->filter->add('wc_get_template_part', [$wp_view, 'template']);
        $this->filter->add('comments_template', [$wp_view, 'reviewsTemplate'], 11);
        $this->filter->add('wc_get_template', [$wp_view, 'template'], 1000);
    }
}
