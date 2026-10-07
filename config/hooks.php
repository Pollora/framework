<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Asynchronous Actions
    |--------------------------------------------------------------------------
    |
    | Action::add(...)->async() and #[Async] queue a handler instead of running
    | it inside the request that fires the hook.
    |
    | default: auto picks the Laravel queue when its connection is not "sync",
    | then Action Scheduler when a plugin bundling it is active, then WP-Cron.
    | Set HOOKS_ASYNC_DRIVER=sync in a developer's .env to run every handler
    | at once, without touching the code.
    |
    | Supported: "auto", "queue", "action-scheduler", "wp-cron", "sync"
    |
    */

    'async' => [

        'default' => env('HOOKS_ASYNC_DRIVER', 'auto'),

        // Attempts when a handler throws, and seconds before each retry (the last value repeats)
        'tries' => 1,
        'backoff' => [10, 60, 300],

        // Run handlers as the user who fired the hook. Off by default: without a user, a capability check fails, which is the safe behaviour
        'as_user' => false,

        'queue' => [
            // null: the default queue connection
            'connection' => env('HOOKS_ASYNC_CONNECTION'),
            // Overridden per action by onQueue() or #[Async(onQueue: ...)]
            'queue' => env('HOOKS_ASYNC_QUEUE', 'default'),
        ],

    ],

];
