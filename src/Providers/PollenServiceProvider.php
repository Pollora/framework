<?php

declare(strict_types=1);

namespace Pollen\Providers;

use Illuminate\Support\ServiceProvider;
use Log1x\SageDirectives\SageDirectivesServiceProvider;
use Pollen\Ajax\AjaxServiceProvider;
use Pollen\Auth\AuthServiceProvider;
use Pollen\Gutenberg\PatternServiceProvider;
use Pollen\Hashing\HashServiceProvider;
use Pollen\Hook\HookServiceProvider;
use Pollen\Http\Request;
use Pollen\Mail\WordPressMailServiceProvider;
use Pollen\PostType\PostTypeServiceProvider;
use Pollen\Taxonomy\TaxonomyServiceProvider;
use Pollen\Theme\ThemeServiceProvider;

/**
 * Registers all the other service providers used by this package.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 * @author Jordan Doyle <jordan@doyle.wf>
 */
class PollenServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function register()
    {
        // override request() method to provide our Request
        $this->app->alias('request', Request::class);

        // Generic service providers
        $this->app->register(WordPressMailServiceProvider::class);
        $this->app->register(HookServiceProvider::class);
        $this->app->register(WordPressServiceProvider::class);
        $this->app->register(AjaxServiceProvider::class);
        $this->app->register(TaxonomyServiceProvider::class);
        $this->app->register(PostTypeServiceProvider::class);
        $this->app->register(ThemeSupportServiceProvider::class);
        $this->app->register(MenuServiceProvider::class);
        $this->app->register(SidebarServiceProvider::class);
        $this->app->register(ConfigServiceProvider::class);
        $this->app->register(QueryServiceProvider::class);
        $this->app->register(SageDirectivesServiceProvider::class);

        // Theme service provider
        $this->app->register(ThemeServiceProvider::class);
        $this->app->register(PatternServiceProvider::class);

        // Authentication service provider
        $this->app->register(AuthServiceProvider::class);

        // Hashing service provider
        $this->app->register(HashServiceProvider::class);
    }

    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/../../public/wp-config.php' => public_path(),
        ], 'public');
    }
}
