<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Module Exception Handling Configuration
    |--------------------------------------------------------------------------
    |
    | This configuration file controls how the Pollora framework handles
    | exceptions and error views from registered modules. It allows you to
    | customize the behavior of the module-aware exception handler.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Error View Resolution Priority
    |--------------------------------------------------------------------------
    |
    | Controls the priority order for error view resolution. When set to true,
    | module error views will take priority over application and framework
    | error views. When false, standard Laravel resolution order is used.
    |
    */
    'prioritize_module_views' => true,

    /*
    |--------------------------------------------------------------------------
    | Exception Reporting Rules
    |--------------------------------------------------------------------------
    |
    | Define custom reporting rules for specific exception types. This allows
    | modules to control whether certain exceptions should be reported or
    | silenced based on their specific needs.
    |
    | Each rule should specify:
    | - 'exception': The fully qualified exception class name
    | - 'report': Boolean indicating whether to report this exception
    |
    */
    'reporting' => [
        // Example: Don't report 404 errors for certain paths
        // [
        //     'exception' => \Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
        //     'report' => false,
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Error View Fallbacks
    |--------------------------------------------------------------------------
    |
    | Define fallback error views for different HTTP status code ranges.
    | These views will be used when no specific status code view is found.
    |
    */
    'fallback_views' => [
        '4xx' => 'errors.4xx',
        '5xx' => 'errors.5xx',
        'client-error' => 'errors.client-error',
        'server-error' => 'errors.server-error',
    ],

    /*
    |--------------------------------------------------------------------------
    | Debug Information
    |--------------------------------------------------------------------------
    |
    | When enabled and in debug mode, additional information about error view
    | resolution will be included in error responses. This is useful for
    | debugging module error view configuration.
    |
    */
    'include_debug_info' => true,

    /*
    |--------------------------------------------------------------------------
    | View Resolution Debugging
    |--------------------------------------------------------------------------
    |
    | Enable detailed logging of the error view resolution process. This is
    | useful for debugging why certain error views are or aren't being found.
    | Only active when app.debug is true.
    |
    */
    'log_view_resolution' => false,
];