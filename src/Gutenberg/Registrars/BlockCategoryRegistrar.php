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

        add_filter('block_categories_all', fn (array $categories): array => array_merge(
            $categories,
            $configuredCategories->map(fn (array $args, string $slug): array => [
                'slug' => $slug,
                'title' => $args['label'] ?? $args['title'] ?? '',
            ])->values()->all()
        ));
    }
}
