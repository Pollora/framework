<?php

declare(strict_types=1);

namespace Pollora\Theme\Infrastructure\Services;

use Pollora\Theme\Domain\Contracts\ThemeJsonResolverInterface;

/**
 * Resolves the Vite-built theme.json from the public build directory.
 *
 * The @roots/vite-plugin wordpressThemeJson plugin generates an enriched
 * theme.json at public/build/theme/{slug}/assets/theme.json. This service
 * reads that file and provides its data for injection into WordPress via
 * the wp_theme_json_data_theme filter.
 */
class ThemeJsonResolver implements ThemeJsonResolverInterface
{
    /**
     * In-memory cache of resolved theme.json data, keyed by theme slug.
     *
     * @var array<string, array|null>
     */
    private array $cache = [];

    public function __construct(
        private readonly string $publicPath
    ) {}

    /**
     * {@inheritDoc}
     */
    public function resolve(string $themeSlug): ?array
    {
        if (array_key_exists($themeSlug, $this->cache)) {
            return $this->cache[$themeSlug];
        }

        $path = $this->getBuildThemeJsonPath($themeSlug);

        if (! file_exists($path)) {
            $this->cache[$themeSlug] = null;

            return null;
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            $this->cache[$themeSlug] = null;

            return null;
        }

        $data = json_decode($contents, true);
        if (! is_array($data)) {
            $this->cache[$themeSlug] = null;

            return null;
        }

        $this->cache[$themeSlug] = $data;

        return $data;
    }

    /**
     * Get the path to the built theme.json file.
     */
    private function getBuildThemeJsonPath(string $themeSlug): string
    {
        return rtrim($this->publicPath, '/').'/build/theme/'.$themeSlug.'/assets/theme.json';
    }
}
