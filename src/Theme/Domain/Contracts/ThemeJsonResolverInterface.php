<?php

declare(strict_types=1);

namespace Pollora\Theme\Domain\Contracts;

/**
 * Resolves the built theme.json data for a given theme.
 *
 * The Vite build process generates an enriched theme.json that includes
 * CSS variables extracted from Tailwind (colors, fonts, etc.). This interface
 * abstracts the resolution of that built file so WordPress can consume it
 * dynamically via the wp_theme_json_data_theme filter.
 */
interface ThemeJsonResolverInterface
{
    /**
     * Resolve the built theme.json data for the given theme slug.
     *
     * @param  string  $themeSlug  The theme directory name (e.g., "apiary")
     * @return array|null The decoded theme.json data, or null if no built file exists
     */
    public function resolve(string $themeSlug): ?array;
}
