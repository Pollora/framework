<?php

declare(strict_types=1);

/**
 * %theme_name% Theme Functions
 *
 * This file registers the theme with the Pollora framework using the new
 * self-registration system. The theme declares itself as active, eliminating
 * the need for database queries to determine the active theme.
 */

// Ensure Pollora framework is loaded
if (! function_exists('pollora_register_theme')) {
    return;
}

// Register this theme as the active theme
try {
    pollora_register_theme('%theme_name%');
} catch (Exception $e) {
    // Log error but don't break the site
    if (function_exists('error_log')) {
        error_log('Failed to register %theme_name% theme: '.$e->getMessage());
    }
}
