<?php

declare(strict_types=1);

namespace Pollora\Providers;

use Illuminate\Support\ServiceProvider;
use Log1x\SageDirectives\SageDirectivesServiceProvider;
use Pollora\Admin\PageServiceProvider;
use Pollora\Ajax\AjaxServiceProvider;
use Pollora\Asset\AssetServiceProvider;
use Pollora\Auth\AuthServiceProvider;
use Pollora\Gutenberg\GutenbergServiceProvider;
use Pollora\Hashing\HashServiceProvider;
use Pollora\Hook\HookServiceProvider;
use Pollora\Mail\WordPressMailServiceProvider;
use Pollora\Permalink\RewriteServiceProvider;
use Pollora\Plugins\WooCommerce\WooCommerceProvider;
use Pollora\PostType\PostTypeServiceProvider;
use Pollora\Scheduler\Jobs\JobDispatcher;
use Pollora\Scheduler\SchedulerServiceProvider;
use Pollora\Taxonomy\TaxonomyServiceProvider;
use Pollora\Theme\ThemeServiceProvider;
use Pollora\View\ViewServiceProvider;
use Pollora\WordPress\WordPressServiceProvider;

/**
 * Main service provider for the Pollora framework.
 *
 * This provider is responsible for registering all core service providers
 * that power the WordPress integration with Laravel. It handles the initialization
 * of various components like views, authentication, post types, and more.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
class PolloraServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * Registers all core service providers including:
     * - WordPress integration services
     * - Theme and view handling
     * - Authentication and hashing
     * - Content type management (posts, taxonomies)
     * - Asset and Gutenberg integration
     * - Scheduling and job dispatching
     *
     * @return void
     */
    public function register(): void
    {
        // Generic service providers
        $this->app->register(ViewServiceProvider::class);
        $this->app->register(GutenbergServiceProvider::class);
        $this->app->register(WordPressMailServiceProvider::class);
        $this->app->register(HookServiceProvider::class);
        $this->app->register(WordPressServiceProvider::class);
        $this->app->register(RewriteServiceProvider::class);
        $this->app->register(PageServiceProvider::class);
        $this->app->register(ThemeServiceProvider::class);
        
        if (!app()->runningInConsole() && !app()->runningInWpCli()) {
            $this->app->register(AssetServiceProvider::class);
            $this->app->register(AjaxServiceProvider::class);
        }
        
        $this->app->register(TaxonomyServiceProvider::class);
        $this->app->register(PostTypeServiceProvider::class);
        $this->app->register(ConfigServiceProvider::class);
        $this->app->register(QueryServiceProvider::class);
        $this->app->register(SageDirectivesServiceProvider::class);
        $this->app->register(WooCommerceProvider::class);

        if (config('wordpress.use_laravel_scheduler')) {
            $this->app->register(SchedulerServiceProvider::class);
        }

        // Authentication service provider
        $this->app->register(AuthServiceProvider::class);

        // Hashing service provider
        $this->app->register(HashServiceProvider::class);
        $this->app->singleton(JobDispatcher::class, fn ($app): \Pollora\Scheduler\Jobs\JobDispatcher => new JobDispatcher($app->make(\Illuminate\Contracts\Bus\Dispatcher::class)));
    }

    /**
     * Perform post-registration booting of services.
     *
     * Publishes necessary configuration files and assets to the public directory.
     * This includes the WordPress configuration file that bridges Laravel and WordPress.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../../public/wp-config.php' => public_path(),
        ], 'public');
    }
}
