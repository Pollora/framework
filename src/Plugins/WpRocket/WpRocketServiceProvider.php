<?php

declare(strict_types=1);

namespace Pollora\Plugins\WpRocket;

use Illuminate\Support\ServiceProvider;

class WpRocketServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->bindFilters();
    }

    private function bindFilters(): void
    {
        $config = config('wordpress.wprocket');

        add_filter('rocket_init_cache_dir_generate_htaccess', $config['generate_htaccess'] ?? false ? static fn (): true => true : static fn (): false => false);
        add_filter('rocket_set_wp_cache_constant', $config['set_cache_constant'] ?? false ? static fn (): true => true : static fn (): false => false);
    }
}
