<?php

declare(strict_types=1);

namespace Pollora\Schedule\Infrastructure\Services;

use Pollora\Schedule\Application\Services\ScheduleStatus;

/**
 * An administration notice for events a former driver still keeps: nothing moves
 * when a page loads, so somebody has to be told to run the migration.
 */
final readonly class MigrationNotice
{
    public function __construct(private ScheduleStatus $status) {}

    /**
     * Show the notice on the administration screens, to those who can run the command.
     */
    public function register(): void
    {
        if (function_exists('add_action')) {
            add_action('admin_notices', $this->render(...));
            add_action('network_admin_notices', $this->render(...));
        }
    }

    public function render(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $pending = $this->status->toMigrate();

        if ($pending === []) {
            return;
        }

        $driver = array_key_first($pending);

        printf(
            '<div class="notice notice-warning"><p><strong>%s</strong> %s <code>%s</code></p></div>',
            esc_html__('Scheduled events are still kept by another driver.', 'pollora'),
            esc_html(sprintf(
                /* translators: 1: number of events, 2: driver name, 3: driver of the site */
                __('%1$d event(s) wait in "%2$s", while the site uses "%3$s". Move them with', 'pollora'),
                array_sum($pending),
                $driver,
                $this->status->active(),
            )),
            esc_html(sprintf('php artisan pollora:schedule:migrate --from=%s --to=%s', $driver, $this->status->active())),
        );
    }
}
