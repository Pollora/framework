<?php

declare(strict_types=1);

// Bootstrap file specifically for PHPStan static analysis
// This file loads only what's needed for static analysis without test mocking

// First we need to load the composer autoloader
require_once dirname(__DIR__) . '/vendor/autoload.php';

// WordPress functions are provided by phpstan-wordpress extension
// No need to load separate stubs file

// Define WordPress constants that may be used in static analysis
if (!defined('WP_PLUGIN_DIR')) {
    define('WP_PLUGIN_DIR', '/var/www/html/wp-content/plugins');
}

if (!defined('WP_CLI_VERSION')) {
    define('WP_CLI_VERSION', '2.13.0');
}

if (!defined('WP_CONTENT_DIR')) {
    define('WP_CONTENT_DIR', '/var/www/html/wp-content');
}

if (!defined('WPINC')) {
    define('WPINC', 'wp-includes');
}

// Define Laravel helper functions for PHPStan static analysis
if (!function_exists('config')) {
    function config($key = null, $default = null) {
        return $default;
    }
}

if (!function_exists('request')) {
    function request($key = null, $default = null) {
        return $default;
    }
}

if (!function_exists('url')) {
    function url($path = null) {
        return 'http://example.com' . ($path ? '/' . ltrim($path, '/') : '');
    }
}

if (!function_exists('storage_path')) {
    function storage_path($path = '') {
        return '/var/www/html/storage' . ($path ? '/' . ltrim($path, '/') : '');
    }
}

if (!function_exists('now')) {
    function now() {
        return new \Illuminate\Support\Carbon();
    }
}

if (!function_exists('public_path')) {
    function public_path($path = '') {
        return '/var/www/html/public' . ($path ? '/' . ltrim($path, '/') : '');
    }
}

if (!function_exists('abort')) {
    function abort($code, $message = '', array $headers = []) {
        throw new \Symfony\Component\HttpKernel\Exception\HttpException($code, $message, null, $headers);
    }
}

// WooCommerce function stub
if (!function_exists('wc_get_order')) {
    function wc_get_order($order_id = null) {
        return null;
    }
}