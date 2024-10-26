<?php

declare(strict_types=1);

namespace Pollora\Providers;

use Illuminate\Support\ServiceProvider;
use Pollora\Models\Post;
use Pollora\Query\DateQuery;
use Pollora\Query\MetaQuery;
use Pollora\Query\PostQuery;
use Pollora\Query\TaxQuery;
use Pollora\Support\WordPress;
use Pollora\View\Loop;

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
        $this->app->bind(\Corcel\Model\User::class, 'Pollora\Model\User');

        $this->app->singleton('wp.query.post', fn (): \Pollora\Query\PostQuery => new PostQuery);

        $this->app->singleton('wp.query.taxonomy', fn (): \Pollora\Query\TaxQuery => new TaxQuery);

        $this->app->singleton('wp.query.meta', fn (): \Pollora\Query\MetaQuery => new MetaQuery);

        $this->app->singleton('wp.query.date', fn (): \Pollora\Query\DateQuery => new DateQuery);

        $this->app->bind('wp.loop', fn (): \Pollora\View\Loop => new Loop);

        $this->app->singleton(Post::class, fn () => Post::find(get_the_ID()));
    }
}
