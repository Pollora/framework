<?php

declare(strict_types=1);

namespace Pollora\Plugins\WooCommerce;

use Illuminate\Support\ServiceProvider;
use Pollora\Hook\Infrastructure\Services\Action;
use Pollora\Hook\Infrastructure\Services\Filter;
use Pollora\Plugins\WooCommerce\View\WooCommerceTemplateResolver;
use Pollora\View\Domain\Contracts\TemplateFinderInterface;

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

        // Register the new WooCommerce template resolver
        $this->app->singleton(WooCommerceTemplateResolver::class, function ($app) {
            return new WooCommerceTemplateResolver(
                $app->make(TemplateFinderInterface::class),
                $app->get('view')
            );
        });
    }

    public function boot(): void
    {
        $this->action->add('plugins_loaded', function (): void {
            if (defined('WC_ABSPATH')) {
                $this->bindFilters();
            }
        });
    }

    public function bindFilters(): void
    {
        $resolver = $this->app->make(WooCommerceTemplateResolver::class);

        // Hook into WooCommerce's own template loader files filter
        // This is where we can inject our Blade templates into WC's search list
        $this->filter->add('woocommerce_template_loader_files', [$resolver, 'extendTemplateLoaderFiles'], 10, 2);
    }
}
