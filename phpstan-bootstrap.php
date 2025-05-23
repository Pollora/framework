<?php

declare(strict_types=1);

if (! function_exists('config_path')) {
    function config_path($path = '')
    {
        return __DIR__.'/config'.($path ? '/'.$path : '');
    }
}
