<?php

declare(strict_types=1);

namespace Pollora\Theme\Infrastructure\Services;

use Pollora\Theme\Domain\Contracts\WordPressThemeInterface;

/**
 * WordPress implementation of the WordPressThemeInterface.
 *
 * This adapter provides access to WordPress theme functions,
 * implementing the domain interface and containing all direct
 * WordPress function calls.
 */
class WordPressThemeAdapter implements WordPressThemeInterface
{
    /**
     * Check if WordPress is in installation mode.
     */
    public function isInstalling(): bool
    {
        return function_exists('wp_installing') && \wp_installing();
    }

    /**
     * Register a theme directory with WordPress.
     */
    public function registerThemeDirectory(string $path): bool
    {
        if (function_exists('register_theme_directory')) {
            return (bool) \register_theme_directory($path);
        }

        return false;
    }

    /**
     * Get the current active theme's stylesheet name.
     */
    public function getStylesheet(): string
    {
        if (function_exists('get_stylesheet')) {
            return \get_stylesheet();
        }

        return 'default';
    }

    /**
     * Get the current parent theme's template name.
     */
    public function getTemplate(): string
    {
        if (function_exists('get_template')) {
            return \get_template();
        }

        return 'default';
    }

    /**
     * Get a WP_Theme instance.
     */
    public function getTheme(?string $themeName = null): object
    {
        if (function_exists('wp_get_theme')) {
            return \wp_get_theme($themeName);
        }

        // Return a placeholder object if WordPress functions are not available
        return new class
        {
            public $stylesheet = 'default';

            public $template = 'default';

            public function get($key, $default = '')
            {
                return $default;
            }
        };
    }

    /**
     * Get WordPress content directories.
     */
    public function getThemeDirectories(): array
    {
        if (defined('WP_CONTENT_DIR')) {
            return [WP_CONTENT_DIR.'/themes'];
        }

        return [];
    }

    /**
     * Get stylesheet directory URI.
     */
    public function getStylesheetDirectoryUri(): string
    {
        if (function_exists('get_stylesheet_directory_uri')) {
            return \get_stylesheet_directory_uri().'/';
        }

        return '/';
    }
}
