<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Scheduling driver
    |--------------------------------------------------------------------------
    |
    | Where scheduled events are kept and who runs them: #[Schedule],
    | Schedule::event(), and every wp_schedule_event() call of WordPress, a
    | plugin or a theme.
    |
    | "wp-cron" is WordPress's own and needs nothing. "action-scheduler" stores
    | events in Action Scheduler (a plugin that bundles it, such as WooCommerce,
    | must be active); without it, events stay on WP-Cron.
    |
    | Changing the driver moves nothing: events already scheduled stay where they
    | are until php artisan pollora:schedule:migrate moves them, and
    | pollora:doctor reminds you.
    |
    | Supported: "wp-cron", "action-scheduler"
    |
    */

    'driver' => env('POLLORA_SCHEDULE_DRIVER', 'wp-cron'),

    /*
    |--------------------------------------------------------------------------
    | Hooks that stay on WP-Cron
    |--------------------------------------------------------------------------
    |
    | Action Scheduler's own queue runner always does: handed to Action
    | Scheduler, it would never run. Add hooks that must run by WP-Cron
    | whatever the driver. The pollora/schedule/protected_hooks filter extends
    | the list too.
    |
    */

    'protected_hooks' => [],

];
