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
     */
    public function register(): void
    {
        $this->app->bind(\Corcel\Model\User::class, 'Pollen\Model\User');

        $this->app->singleton('wp.query.post', fn(): \Pollen\Query\PostQuery => new PostQuery);

        $this->app->singleton('wp.query.taxonomy', fn(): \Pollen\Query\TaxQuery => new TaxQuery);

        $this->app->singleton('wp.query.meta', fn(): \Pollen\Query\MetaQuery => new MetaQuery);

        $this->app->singleton('wp.query.date', fn(): \Pollen\Query\DateQuery => new DateQuery);

        $this->app->bind('wp.loop', fn(): \Pollen\View\Loop => new Loop);

        $this->app->singleton(Post::class, fn() => Post::find(get_the_ID()));
    }
}
