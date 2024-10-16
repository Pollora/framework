<?php

declare(strict_types=1);

namespace Pollen\Providers;

use Illuminate\Support\ServiceProvider;
use Log1x\SageDirectives\SageDirectivesServiceProvider;
use Pollen\Admin\PageServiceProvider;
use Pollen\Ajax\AjaxServiceProvider;
use Pollen\Asset\AssetServiceProvider;
use Pollen\Auth\AuthServiceProvider;
use Pollen\Gutenberg\GutenbergServiceProvider;
use Pollen\Hashing\HashServiceProvider;
use Pollen\Hook\HookServiceProvider;
use Pollen\Mail\WordPressMailServiceProvider;
use Pollen\Permalink\RewriteServiceProvider;
use Pollen\PostType\PostTypeServiceProvider;
use Pollen\Scheduler\Jobs\JobDispatcher;
use Pollen\Scheduler\SchedulerServiceProvider;
use Pollen\Taxonomy\TaxonomyServiceProvider;
use Pollen\Theme\ThemeServiceProvider;
use Pollen\View\ViewServiceProvider;

/**
 * Registers all the other service providers used by this package.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
class PollenServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
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
        $this->app->register(AssetServiceProvider::class);
        $this->app->register(AjaxServiceProvider::class);
        $this->app->register(TaxonomyServiceProvider::class);
        $this->app->register(PostTypeServiceProvider::class);
        $this->app->register(ConfigServiceProvider::class);
        $this->app->register(QueryServiceProvider::class);
        $this->app->register(SageDirectivesServiceProvider::class);

        if (config('wordpress.use_laravel_scheduler')) {
            $this->app->register(SchedulerServiceProvider::class);
        }

        // Authentication service provider
        $this->app->register(AuthServiceProvider::class);

        // Hashing service provider
        $this->app->register(HashServiceProvider::class);
        $this->app->singleton(JobDispatcher::class, fn($app): \Pollen\Scheduler\Jobs\JobDispatcher => new JobDispatcher($app->make(\Illuminate\Contracts\Bus\Dispatcher::class)));
    }

    /**
     * Perform post-registration booting of services.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../../public/wp-config.php' => public_path(),
        ], 'public');
    }
}
