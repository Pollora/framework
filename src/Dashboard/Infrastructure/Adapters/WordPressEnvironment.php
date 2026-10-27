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
        return function_exists('get_locale') && function_exists('wp_cache_get') ? (string) get_locale() : null;
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
        $object = function_exists('get_post_type_object') ? get_post_type_object($slug) : null;

        return $object !== null && isset($object->labels->name) ? $object->labels->name : null;
    }

    public function taxonomyLabel(string $slug): ?string
    {
        $object = function_exists('get_taxonomy') ? get_taxonomy($slug) : false;

        return $object !== false && isset($object->labels->name) ? $object->labels->name : null;
    }

    public function activeTheme(): ?array
    {
        $theme = function_exists('wp_get_theme') ? wp_get_theme() : null;

        return $theme === null ? null : [
            'name' => (string) $theme->get('Name'),
            'version' => (string) $theme->get('Version'),
            'template' => (string) $theme->get_template(),
        ];
    }
}
