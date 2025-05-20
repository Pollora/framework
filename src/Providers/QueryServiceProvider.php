<?php

declare(strict_types=1);

namespace Pollora\Providers;

use Illuminate\Support\ServiceProvider;
use Pollora\Colt\Model\User as BaseUser;
use Pollora\Container\Domain\ServiceLocator;
use Pollora\Models\Post;
use Pollora\Models\User;
use Pollora\Query\DateQuery;
use Pollora\Query\MetaQuery;
use Pollora\Query\PostQuery;
use Pollora\Query\TaxQuery;
use Pollora\View\Loop;

/**
 * Service provider for WordPress query functionality.
 *
 * This provider registers various query-related services that handle WordPress
 * query operations, including:
 * - Post queries
 * - Taxonomy queries
 * - Meta queries
 * - Date queries
 * - WordPress loop functionality
 */
class QueryServiceProvider extends ServiceProvider
{
    /**
     * Register query-related services.
     *
     * Binds various query classes and utilities to the service container:
     * - Custom user model binding
     * - Post query singleton
     * - Taxonomy query singleton
     * - Meta query singleton
     * - Date query singleton
     * - WordPress loop binding
     * - Current post binding
     */
    public function register(): void
    {
        // User model binding
        $this->app->bind(BaseUser::class, User::class);

        // Query singletons
        $this->app->singleton('wp.query.post', fn (): PostQuery => new PostQuery);
        $this->app->singleton('wp.query.taxonomy', fn (): TaxQuery => new TaxQuery);
        $this->app->singleton('wp.query.meta', fn (): MetaQuery => new MetaQuery);
        $this->app->singleton('wp.query.date', fn (): DateQuery => new DateQuery);

        // Loop and current post bindings
        $this->app->bind('wp.loop', fn ($app): Loop => new Loop($app));
        $this->app->singleton(Post::class, fn () => Post::find(get_the_ID()));
    }
}
