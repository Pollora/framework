<?php

declare(strict_types=1);

namespace Pollen\Providers;

use Illuminate\Support\ServiceProvider;
use Pollen\Models\Post;
use Pollen\Query\DateQuery;
use Pollen\Query\MetaQuery;
use Pollen\Query\PostQuery;
use Pollen\Query\TaxQuery;
use Pollen\Support\WordPress;
use Pollen\View\Loop;

/**
 * Service provider that provides bindings for the several queries that WordPress
 * has running at once.
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

        $this->app->singleton('wp.query.post', function () {
            return new PostQuery();
        });

        $this->app->singleton('wp.query.taxonomy', function () {
            return new TaxQuery();
        });

        $this->app->singleton('wp.query.meta', function () {
            return new MetaQuery();
        });

        $this->app->singleton('wp.query.date', function () {
            return new DateQuery();
        });

        $this->app->bind('wp.loop', function () {
            return new Loop();
        });

        $this->app->singleton(Post::class, function () {
            return Post::find(get_the_ID());
        });
    }
}
