<?php

declare(strict_types=1);

namespace Pollora\Dashboard\Infrastructure\Adapters;

use Pollora\Dashboard\Domain\Contracts\WordPressEnvironmentInterface;

/**
 * Reads the dashboard's WordPress facts from WordPress itself.
 *
 * Every call is guarded by function_exists(): the dashboard and
 * `pollora:status` also run before WordPress is loaded, or without it.
 */
final readonly class WordPressEnvironment implements WordPressEnvironmentInterface
{
    public function locale(): ?string
    {
        // get_locale() reads the object cache, which is not there before
        // WordPress has loaded far enough to answer.
        if (! function_exists('get_locale') || ! function_exists('wp_cache_get')) {
            return null;
        }

        return (string) get_locale();
    }

    public function version(): ?string
    {
        return function_exists('get_bloginfo') ? (string) get_bloginfo('version') : null;
    }

    public function isMultisite(): bool
    {
        return function_exists('is_multisite') && is_multisite();
    }

    public function permalinkStructure(): string
    {
        return function_exists('get_option') ? (string) get_option('permalink_structure', '') : '';
    }

    public function postTypeLabel(string $slug): ?string
    {
        if (! function_exists('get_post_type_object')) {
            return null;
        }

        $object = get_post_type_object($slug);

        return $object !== null && isset($object->labels->name) ? $object->labels->name : null;
    }

    public function taxonomyLabel(string $slug): ?string
    {
        if (! function_exists('get_taxonomy')) {
            return null;
        }

        $object = get_taxonomy($slug);

        return $object !== false && isset($object->labels->name) ? $object->labels->name : null;
    }

    public function activeTheme(): ?array
    {
        if (! function_exists('wp_get_theme')) {
            return null;
        }

        $theme = wp_get_theme();

        return [
            'name' => (string) $theme->get('Name'),
            'version' => (string) $theme->get('Version'),
            'template' => (string) $theme->get_template(),
        ];
    }
}
