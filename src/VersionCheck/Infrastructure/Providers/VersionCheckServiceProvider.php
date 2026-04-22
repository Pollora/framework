<?php

declare(strict_types=1);

namespace Pollora\VersionCheck\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Pollora\Hook\Domain\Contracts\Action;
use Pollora\Hook\Domain\Contracts\Filter;
use Pollora\VersionCheck\Domain\Contracts\VersionCheckerInterface;
use Pollora\VersionCheck\Domain\Services\VersionComparator;
use Pollora\VersionCheck\Infrastructure\Services\PackagistVersionChecker;
use Pollora\VersionCheck\UI\Http\AdminNotice;
use Pollora\VersionCheck\UI\Http\SiteHealthCheck;

/**
 * Service provider for the Pollora version check module.
 *
 * Registers version checking services in the container and hooks the
 * admin notice and Site Health integration into WordPress when running
 * in the admin context.
 *
 * Hooks registered:
 * - `admin_notices`: Displays a dismissable update banner
 * - `wp_ajax_pollora_dismiss_update_notice`: Handles notice dismissal via AJAX
 * - `debug_information`: Adds Pollora version info to Site Health debug data
 * - `site_status_tests`: Adds a version status test to Site Health
 */
class VersionCheckServiceProvider extends ServiceProvider
{
    /**
     * Register version check services in the container.
     *
     * Binds the VersionCheckerInterface to the Packagist implementation
     * and registers the VersionComparator as a singleton.
     */
    public function register(): void
    {
        $this->app->singleton(VersionCheckerInterface::class, PackagistVersionChecker::class);
        $this->app->singleton(VersionComparator::class);
    }

    /**
     * Boot version check hooks into WordPress admin.
     *
     * Only registers hooks when running in the WordPress admin context
     * to avoid unnecessary overhead on frontend requests.
     *
     * @param  Action  $action  WordPress action hook service
     * @param  Filter  $filter  WordPress filter hook service
     */
    public function boot(Action $action, Filter $filter): void
    {
        if (! function_exists('is_admin') || ! is_admin()) {
            return;
        }

        $action->add('admin_notices', [$this->app->make(AdminNotice::class), 'render']);
        $action->add('wp_ajax_pollora_dismiss_update_notice', [$this->app->make(AdminNotice::class), 'dismiss']);

        $filter->add('debug_information', [$this->app->make(SiteHealthCheck::class), 'addDebugInfo']);
        $filter->add('site_status_tests', [$this->app->make(SiteHealthCheck::class), 'addTests']);
    }
}
