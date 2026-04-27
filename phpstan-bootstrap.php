<?php

declare(strict_types=1);

if (! defined('LARAVEL_VERSION')) {
    define('LARAVEL_VERSION', '13.0.0');
}

if (! defined('AUTH_COOKIE')) {
    define('AUTH_COOKIE', 'wordpress_logged_in');
}

if (! defined('SECURE_AUTH_COOKIE')) {
    define('SECURE_AUTH_COOKIE', 'wordpress_sec');
}

if (! defined('WPINC')) {
    define('WPINC', 'wp-includes');
}

if (! defined('WP_CLI_VERSION')) {
    define('WP_CLI_VERSION', '2.0.0');
}

if (! function_exists('config_path')) {
    function config_path($path = '')
    {
        return __DIR__.'/config'.($path ? '/'.$path : '');
    }
}
