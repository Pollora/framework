<?php

declare(strict_types=1);

namespace Pollora\Gutenberg\Registrars;

/**
 * Registrar for Gutenberg block categories.
 *
 * Handles the registration of custom block categories
 * defined in the theme configuration.
 */
class BlockCategoryRegistrar
{
    /**
     * Register configured block categories.
     *
     * Reads categories from theme configuration and registers them
     * in WordPress through the 'block_categories_all' filter.
     */
    public function register(): void
    {
        $configuredCategories = collect(config('theme.gutenberg.categories.blocks'));

        add_filter('block_categories_all', function ($categories) use ($configuredCategories) {
            return array_merge(
                $categories,
                $configuredCategories->map(function ($args, $slug) {
                    return [
                        'slug' => $slug,
                        'title' => $args['label'] ?? $args['title'] ?? '',
                    ];
                })->values()->all()
            );
        });
    }
}
