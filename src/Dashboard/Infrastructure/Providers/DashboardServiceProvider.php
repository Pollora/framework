<?php

declare(strict_types=1);

namespace Pollora\Dashboard\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Pollora\Dashboard\Domain\Services\SystemInfoCollector;
use Pollora\Dashboard\UI\Console\StatusCommand;
use Pollora\Dashboard\UI\Http\DashboardController;
use Pollora\Hook\Domain\Contracts\Action;
use Pollora\VersionCheck\Domain\Services\VersionComparator;

/**
 * Service provider for the Pollora admin dashboard.
 *
 * Registers the dashboard admin page under the WordPress admin menu
 * and the `pollora:status` Artisan command. Shows a notification badge
 * on the menu when a framework update is available.
 */
class DashboardServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SystemInfoCollector::class);

        if ($this->app->runningInConsole()) {
            $this->commands([StatusCommand::class]);
        }
    }

    public function boot(Action $action): void
    {
        if (! function_exists('is_admin') || ! is_admin()) {
            return;
        }

        $action->add('admin_menu', function (): void {
            $menuTitle = $this->buildMenuTitle();

            add_submenu_page(
                'tools.php',
                __('Pollora', 'pollora'),
                $menuTitle,
                'manage_options',
                'pollora-dashboard',
                $this->app->make(DashboardController::class)
            );
        });
    }

    /**
     * Build the menu title with an optional notification badge.
     *
     * Shows a counter badge (like Site Health) when a framework update
     * is available, drawing attention without being intrusive.
     */
    private function buildMenuTitle(): string
    {
        $title = __('Pollora', 'pollora');
        $notifications = $this->countNotifications();

        if ($notifications === 0) {
            return $title;
        }

        return sprintf(
            '%s <span class="update-plugins count-%d"><span class="plugin-count">%d</span></span>',
            $title,
            $notifications,
            $notifications
        );
    }

    /**
     * Count the number of active notifications (update available, etc.).
     */
    private function countNotifications(): int
    {
        $count = 0;

        try {
            $comparator = $this->app->make(VersionComparator::class);
            $current = $comparator->getCurrentVersion();
            $isDev = is_string($current) && str_starts_with($current, 'dev-');

            if (! $isDev && $comparator->isUpdateAvailable()) {
                $count++;
            }
        } catch (\Throwable) {
            // Silently ignore if version check is unavailable
        }

        return $count;
    }
}
