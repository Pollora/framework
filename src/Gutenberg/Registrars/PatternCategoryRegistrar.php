<?php

declare(strict_types=1);

namespace Pollora\Gutenberg\Registrars;

/**
 * Registrar for block pattern categories.
 *
 * Handles the registration of custom block pattern categories
 * defined in the theme configuration.
 */
class PatternCategoryRegistrar
{
    /**
     * Register configured block pattern categories.
     *
     * Reads categories from theme configuration and registers them
     * with WordPress.
     */
    public function register(): void
    {
        collect(config('theme.gutenberg.categories.patterns'))
            ->each(fn ($args, $key) => register_block_pattern_category($key, $args));
    }
}
