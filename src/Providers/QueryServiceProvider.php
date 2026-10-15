<?php

declare(strict_types=1);

namespace Pollora\Providers;

use Illuminate\Support\ServiceProvider;
use Pollora\Colt\Model\User as BaseUser;
use Pollora\Models\Post;
use Pollora\Models\User;
use Pollora\Query\CommentQuery;
use Pollora\Query\DateQuery;
use Pollora\Query\MetaQuery;
use Pollora\Query\NetworkQuery;
use Pollora\Query\PostQuery;
use Pollora\Query\SiteQuery;
use Pollora\Query\TaxQuery;
use Pollora\Query\TermQuery;
use Pollora\Query\UserQuery;

/**
 * Service provider for WordPress query functionality.
 *
 * This provider registers various query-related services that handle WordPress
 * query operations, including:
 * - Post queries
 * - Taxonomy queries
 * - Meta queries
 * - Date queries
 * - Term, user, comment, site and network queries
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
     * - Term, user, comment, site and network query bindings
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

        // A query builder keeps what it is given, so these are bound, not shared, and their facades do not
        // cache the instance: `TermQuery::taxonomy('category')` cannot leak into the next query.
        $this->app->bind('wp.query.term', fn (): TermQuery => new TermQuery);
        $this->app->bind('wp.query.user', fn (): UserQuery => new UserQuery);
        $this->app->bind('wp.query.comment', fn (): CommentQuery => new CommentQuery);
        $this->app->bind('wp.query.site', fn (): SiteQuery => new SiteQuery);
        $this->app->bind('wp.query.network', fn (): NetworkQuery => new NetworkQuery);

        // Current post binding
        $this->app->singleton(Post::class, fn () => Post::find(get_the_ID()));
    }
}
