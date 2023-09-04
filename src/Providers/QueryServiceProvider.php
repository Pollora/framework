<?php

declare(strict_types=1);

namespace Pollen\Providers;

use Illuminate\Support\ServiceProvider;
use Pollen\Models\Post;
use Pollen\Proxy\Query;
use Pollen\Support\WordPress;
use Pollen\View\Loop;

/**
 * Service provider that provides bindings for the several queries that WordPress
 * has running at once.
 *
 * @author Jordan Doyle <jordan@doyle.wf>
 */
class QueryServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind('Corcel\Model\User', 'Pollen\Model\User');

        $this->app->singleton('wp.query', function () {
            return Query::instance($GLOBALS['wp_the_query']);
        });

        $this->app->bind('wp.loop', function () {
            return new Loop();
        });

        $this->app->singleton(Post::class, function () {
            return Post::find(WordPress::id());
        });
    }
}
