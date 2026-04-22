<?php

declare(strict_types=1);

if (! defined('LARAVEL_VERSION')) {
    define('LARAVEL_VERSION', '13.0.0');
}

if (! function_exists('config_path')) {
    function config_path($path = '')
    {
        return __DIR__.'/config'.($path ? '/'.$path : '');
    }
}
