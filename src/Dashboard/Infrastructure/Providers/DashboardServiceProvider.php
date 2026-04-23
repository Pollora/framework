<?php

declare(strict_types=1);

namespace Pollora\Dashboard\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Pollora\Dashboard\Domain\Services\SystemInfoCollector;
use Pollora\Dashboard\UI\Console\StatusCommand;
use Pollora\Dashboard\UI\Http\DashboardController;
use Pollora\Hook\Domain\Contracts\Action;

/**
 * Service provider for the Pollora admin dashboard.
 *
 * Registers the dashboard admin page under the WordPress admin menu
 * and the `pollora:status` Artisan command.
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
            add_submenu_page(
                'tools.php',
                __('Pollora', 'pollora'),
                __('Pollora', 'pollora'),
                'manage_options',
                'pollora-dashboard',
                $this->app->make(DashboardController::class)
            );
        });
    }
}
