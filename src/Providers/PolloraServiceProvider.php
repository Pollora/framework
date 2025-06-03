<?php

declare(strict_types=1);

namespace Pollora\Providers;

use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\ServiceProvider;
use Log1x\SageDirectives\SageDirectivesServiceProvider;
use Pollora\Admin\PageServiceProvider;
use Pollora\Ajax\Infrastructure\Providers\AjaxServiceProvider;
use Pollora\Application\Infrastructure\Providers\ConsoleServiceProvider;
use Pollora\Application\Infrastructure\Providers\DebugServiceProvider;
use Pollora\Asset\Infrastructure\Providers\AssetServiceProvider;
use Pollora\Attributes\AttributesServiceProvider;
use Pollora\Auth\AuthServiceProvider;
use Pollora\BlockCategory\Infrastructure\Providers\BlockCategoryServiceProvider;
use Pollora\BlockPattern\Infrastructure\Providers\BlockPatternServiceProvider;
use Pollora\Collection\Infrastructure\Providers\CollectionServiceProvider;
use Pollora\Config\Infrastructure\Providers\ConfigServiceProvider;
use Pollora\Discoverer\Infrastructure\Providers\DiscovererServiceProvider;
use Pollora\Events\WordPress\WordPressEventServiceProvider;
use Pollora\Hashing\HashServiceProvider;
use Pollora\Hook\Infrastructure\Providers\HookServiceProvider;
use Pollora\Mail\WordPressMailServiceProvider;
use Pollora\Modules\ModuleServiceProvider;
use Pollora\Permalink\RewriteServiceProvider;
use Pollora\Plugins\WooCommerce\WooCommerceProvider;
use Pollora\Plugins\WpRocket\WpRocketServiceProvider;
use Pollora\PostType\Infrastructure\Providers\PostTypeAttributeServiceProvider;
use Pollora\PostType\Infrastructure\Providers\PostTypeServiceProvider;
use Pollora\Route\Infrastructure\Providers\RouteServiceProvider;
use Pollora\Scheduler\Jobs\JobDispatcher;
use Pollora\Scheduler\SchedulerServiceProvider;
use Pollora\Taxonomy\Infrastructure\Providers\TaxonomyAttributeServiceProvider;
use Pollora\Taxonomy\Infrastructure\Providers\TaxonomyServiceProvider;
use Pollora\Theme\Infrastructure\Providers\ThemeServiceProvider;
use Pollora\View\ViewServiceProvider;
use Pollora\WordPress\Config\ConstantServiceProvider;
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
     * - ...
     */
    public function register(): void
    {
        // Generic service providers
        $this->app->register(ConsoleServiceProvider::class);
        $this->app->register(DebugServiceProvider::class);
        $this->app->register(DiscovererServiceProvider::class);
        $this->app->register(ModuleServiceProvider::class);
        $this->app->register(ConstantServiceProvider::class);
        $this->app->register(AttributesServiceProvider::class);
        $this->app->register(ViewServiceProvider::class);

        $this->app->register(TaxonomyServiceProvider::class);
        $this->app->register(TaxonomyAttributeServiceProvider::class);

        $this->app->register(PostTypeServiceProvider::class);
        $this->app->register(PostTypeAttributeServiceProvider::class);

        // Shared modules
        $this->app->register(CollectionServiceProvider::class);

        // Block features
        $this->app->register(BlockCategoryServiceProvider::class);
        $this->app->register(BlockPatternServiceProvider::class);

        $this->app->register(WordPressMailServiceProvider::class);
        $this->app->register(HookServiceProvider::class);

        $this->app->register(RewriteServiceProvider::class);
        $this->app->register(PageServiceProvider::class);
        $this->app->register(ThemeServiceProvider::class);
        $this->app->register(AssetServiceProvider::class);
        $this->app->register(AjaxServiceProvider::class);
        $this->app->register(ConfigServiceProvider::class);
        $this->app->register(QueryServiceProvider::class);
        $this->app->register(SageDirectivesServiceProvider::class);
        $this->app->register(WooCommerceProvider::class);
        $this->app->register(WpRocketServiceProvider::class);
        $this->app->register(WordPressEventServiceProvider::class);
        $this->app->register(RouteServiceProvider::class);

        if (config('wordpress.use_laravel_scheduler', false)) {
            $this->app->register(SchedulerServiceProvider::class);
        }
        $this->app->singleton(JobDispatcher::class, fn ($app): JobDispatcher => new JobDispatcher($app->make(Dispatcher::class)));

        // Authentication service provider
        $this->app->register(AuthServiceProvider::class);

        // Hashing service provider
        $this->app->register(HashServiceProvider::class);
        $this->app->register(WordPressServiceProvider::class);
    }

    /**
     * Perform post-registration booting of services.
     *
     * Publishes necessary configuration files and assets to the public directory.
     * This includes the WordPress configuration file that bridges Laravel and WordPress.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../../public/wp-config.php' => public_path(),
        ], 'public');
    }
}
