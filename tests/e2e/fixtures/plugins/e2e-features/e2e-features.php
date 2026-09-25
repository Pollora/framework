<?php

/**
 * Plugin Name: E2E Features
 * Description: Pollora browser test fixture — every framework feature it declares has an effect a test can see. Never activate it on a real site.
 * Version: 1.0.0
 * License: MIT
 */

declare(strict_types=1);

use Pollora\Modules\Domain\Enums\ModuleType;

if (! defined('ABSPATH')) {
    exit;
}

pollora_register(ModuleType::Plugin, 'e2e-features', __DIR__);
