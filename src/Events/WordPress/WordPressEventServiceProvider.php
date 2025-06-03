<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress;

use Illuminate\Support\ServiceProvider;
use Pollora\Events\WordPress\Blog\BlogEventDispatcher;
use Pollora\Events\WordPress\Comment\CommentEventDispatcher;
use Pollora\Events\WordPress\Installer\InstallerEventDispatcher;
use Pollora\Events\WordPress\Media\MediaEventDispatcher;
use Pollora\Events\WordPress\Menu\MenuEventDispatcher;
use Pollora\Events\WordPress\Option\OptionEventDispatcher;
use Pollora\Events\WordPress\Plugins\TwoFactor\TwoFactorEventDispatcher;
use Pollora\Events\WordPress\Plugins\UserSwitching\UserSwitchingEventDispatcher;
use Pollora\Events\WordPress\Plugins\WooCommerce\WooCommerceEventDispatcher;
use Pollora\Events\WordPress\Plugins\YoastSeo\YoastSeoEventDispatcher;
use Pollora\Events\WordPress\Post\PostEventDispatcher;
use Pollora\Events\WordPress\Taxonomy\TaxonomyEventDispatcher;
use Pollora\Events\WordPress\User\UserEventDispatcher;
use Pollora\Events\WordPress\Widget\WidgetEventDispatcher;

/**
 * Service provider responsible for registering WordPress event dispatchers.
 *
 * This provider initializes the event dispatching system that bridges WordPress hooks
 * with Laravel events. It registers various event dispatchers for different WordPress
 * components (posts, terms, options, etc.).
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
class WordPressEventServiceProvider extends ServiceProvider
{
    /**
     * Register WordPress event dispatchers.
     */
    public function register(): void
    {
        $this->app->singleton(PostEventDispatcher::class);
        $this->app->singleton(TaxonomyEventDispatcher::class);
        $this->app->singleton(MediaEventDispatcher::class);
        $this->app->singleton(UserEventDispatcher::class);
        $this->app->singleton(MenuEventDispatcher::class);
        $this->app->singleton(WidgetEventDispatcher::class);
        $this->app->singleton(OptionEventDispatcher::class);
        $this->app->singleton(CommentEventDispatcher::class);
        $this->app->singleton(BlogEventDispatcher::class);
        $this->app->singleton(InstallerEventDispatcher::class);
        $this->app->singleton(WooCommerceEventDispatcher::class);
        $this->app->singleton(YoastSeoEventDispatcher::class);
        $this->app->singleton(TwoFactorEventDispatcher::class);
        $this->app->singleton(UserSwitchingEventDispatcher::class);
    }

    /**
     * Bootstrap WordPress event dispatchers.
     */
    public function boot(): void
    {
        $this->app->make(PostEventDispatcher::class)->registerEvents();
        $this->app->make(TaxonomyEventDispatcher::class)->registerEvents();
        $this->app->make(MediaEventDispatcher::class)->registerEvents();
        $this->app->make(UserEventDispatcher::class)->registerEvents();
        $this->app->make(MenuEventDispatcher::class)->registerEvents();
        $this->app->make(WidgetEventDispatcher::class)->registerEvents();
        $this->app->make(OptionEventDispatcher::class)->registerEvents();
        $this->app->make(CommentEventDispatcher::class)->registerEvents();
        $this->app->make(BlogEventDispatcher::class)->registerEvents();
        $this->app->make(InstallerEventDispatcher::class)->registerEvents();
        $this->app->make(WooCommerceEventDispatcher::class)->registerEvents();
        $this->app->make(YoastSeoEventDispatcher::class)->registerEvents();
        $this->app->make(TwoFactorEventDispatcher::class)->registerEvents();
        $this->app->make(UserSwitchingEventDispatcher::class)->registerEvents();
    }
}
