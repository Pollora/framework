<?php

declare(strict_types=1);

// Load WordPress function stubs BEFORE Composer autoload so they take
// precedence over the real implementations from laravel/framework.
// Both use function_exists() guards, so first-loaded wins.
require_once __DIR__.'/Unit/helpers.php';

require_once __DIR__.'/../vendor/autoload.php';
