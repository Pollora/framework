<?php

declare(strict_types=1);

namespace Pollora\Theme\Infrastructure\Services;

use Pollora\Theme\Domain\Contracts\WordPressThemeInterface;

/**
 * WordPress implementation of the WordPressThemeInterface.
 *
 * This adapter provides access to WordPress theme functions,
 * implementing the domain interface and containing all direct
 * WordPress function calls with enhanced error handling and robustness.
 */
class WordPressThemeAdapter implements WordPressThemeInterface
{
    public const DEFAULT_THEME_NAME = 'default';

    public const DEFAULT_URI = '/';

    /**
     * Check if WordPress is in installation mode.
     *
     * @return bool True if WordPress is currently being installed, false otherwise
     */
    public function isInstalling(): bool
    {
        return $this->isFunctionAvailable('wp_installing') && \wp_installing();
    }

    /**
     * Register a theme directory with WordPress.
     *
     * @param  string  $path  The absolute path to the theme directory
     * @return bool True on success, false on failure or if function unavailable
     */
    public function registerThemeDirectory(string $path): bool
    {
        if (empty($path) || ! $this->isFunctionAvailable('register_theme_directory')) {
            return false;
        }

        // Ensure the path is properly formatted and exists
        $sanitizedPath = $this->sanitizePath($path);
        if (! $sanitizedPath || ! is_dir($sanitizedPath)) {
            return false;
        }

        try {
            return \register_theme_directory($sanitizedPath);
        } catch (\Throwable $e) {
            // Log error if logging is available but don't break execution
            if ($this->isFunctionAvailable('error_log')) {
                \error_log("Failed to register theme directory: {$e->getMessage()}");
            }

            return false;
        }
    }

    /**
     * Get the current active theme's stylesheet name.
     *
     * @return string The stylesheet name or default fallback
     */
    public function getStylesheet(): string
    {
        if (! $this->isFunctionAvailable('get_stylesheet')) {
            return self::DEFAULT_THEME_NAME;
        }

        try {
            $stylesheet = \get_stylesheet();

            return ! empty($stylesheet) ? $stylesheet : self::DEFAULT_THEME_NAME;
        } catch (\Throwable $e) {
            return self::DEFAULT_THEME_NAME;
        }
    }

    /**
     * Get the current parent theme's template name.
     *
     * @return string The template name or default fallback
     */
    public function getTemplate(): string
    {
        if (! $this->isFunctionAvailable('get_template')) {
            return self::DEFAULT_THEME_NAME;
        }

        try {
            $template = \get_template();

            return ! empty($template) ? $template : self::DEFAULT_THEME_NAME;
        } catch (\Throwable $e) {
            return self::DEFAULT_THEME_NAME;
        }
    }

    /**
     * Get a WP_Theme instance.
     *
     * @param  string|null  $themeName  Optional theme name to get. If null, gets current theme
     * @return object WP_Theme instance or fallback object
     */
    public function getTheme(?string $themeName = null): object
    {
        if (! $this->isFunctionAvailable('wp_get_theme')) {
            return $this->createFallbackThemeObject();
        }

        try {
            $theme = \wp_get_theme($themeName);

            // Verify we got a valid theme object
            return $theme instanceof \WP_Theme ? $theme : $this->createFallbackThemeObject();
        } catch (\Throwable $e) {
            return $this->createFallbackThemeObject();
        }
    }

    /**
     * Get WordPress content directories for themes.
     *
     * @return array Array of theme directory paths
     */
    public function getThemeDirectories(): array
    {
        $directories = [];

        // Check for WP_CONTENT_DIR constant
        if (defined('WP_CONTENT_DIR') && ! empty(WP_CONTENT_DIR)) {
            $themesDir = rtrim(WP_CONTENT_DIR, '/\\').'/themes';
            if (is_dir($themesDir)) {
                $directories[] = $themesDir;
            }
        }

        // Fallback: try to get from WordPress functions if available
        if (empty($directories) && $this->isFunctionAvailable('get_theme_root')) {
            try {
                $themeRoot = \get_theme_root();
                if (! empty($themeRoot) && is_dir($themeRoot)) {
                    $directories[] = $themeRoot;
                }
            } catch (\Throwable $e) {
                // Silent fallback
            }
        }

        // Additional fallback: check common WordPress installation paths
        if (empty($directories)) {
            $commonPaths = [
                ABSPATH.'wp-content/themes' ?? '',
                __DIR__.'/../../../../../wp-content/themes',
            ];

            foreach ($commonPaths as $path) {
                if (! empty($path) && is_dir($path)) {
                    $directories[] = $path;
                    break;
                }
            }
        }

        return array_unique(array_filter($directories));
    }

    /**
     * Get stylesheet directory URI.
     *
     * @return string The stylesheet directory URI with trailing slash
     */
    public function getStylesheetDirectoryUri(): string
    {
        if (! $this->isFunctionAvailable('get_stylesheet_directory_uri')) {
            return self::DEFAULT_URI;
        }

        try {
            $uri = \get_stylesheet_directory_uri();
            if (empty($uri)) {
                return self::DEFAULT_URI;
            }

            // Ensure trailing slash for consistency
            return rtrim($uri, '/').'/';
        } catch (\Throwable $e) {
            return self::DEFAULT_URI;
        }
    }

    /**
     * Check if a WordPress function is available.
     *
     * @param  string  $functionName  The function name to check
     * @return bool True if function exists and is callable
     */
    private function isFunctionAvailable(string $functionName): bool
    {
        return ! empty($functionName) &&
            function_exists($functionName) &&
            is_callable($functionName);
    }

    /**
     * Sanitize and normalize a file path.
     *
     * @param  string  $path  The path to sanitize
     * @return string|null The sanitized path or null if invalid
     */
    private function sanitizePath(string $path): ?string
    {
        if (empty($path)) {
            return null;
        }

        // Remove any potential directory traversal attempts
        $sanitized = str_replace(['../', '..\\'], '', $path);

        // Normalize directory separators
        $sanitized = str_replace('\\', '/', $sanitized);

        // Remove multiple consecutive slashes
        $sanitized = preg_replace('#/+#', '/', $sanitized);

        // Real path resolution if possible
        if (file_exists($sanitized)) {
            $realPath = realpath($sanitized);
            if ($realPath !== false) {
                return $realPath;
            }
        }

        return $sanitized;
    }

    /**
     * Create a fallback theme object when WordPress functions are unavailable.
     *
     * @return object Anonymous object mimicking WP_Theme interface
     */
    private function createFallbackThemeObject(): object
    {
        return new class
        {
            public string $stylesheet = WordPressThemeAdapter::DEFAULT_THEME_NAME;

            public string $template = WordPressThemeAdapter::DEFAULT_THEME_NAME;

            private array $data = [];

            /**
             * Get theme data with fallback.
             *
             * @param  string  $key  The data key to retrieve
             * @param  mixed|string  $default  Default value if key not found
             * @return mixed The theme data value or default
             */
            public function get(string $key, $default = '')
            {
                if (empty($key)) {
                    return $default;
                }

                return $this->data[$key] ?? $default;
            }

            /**
             * Check if theme exists (always false for fallback).
             *
             * @return bool Always returns false for fallback object
             */
            public function exists(): bool
            {
                return false;
            }

            /**
             * Get theme name with fallback.
             *
             * @return string Default theme name
             */
            public function get_name(): string
            {
                return 'Default Theme';
            }

            /**
             * Get theme version with fallback.
             *
             * @return string Default version
             */
            public function get_version(): string
            {
                return '1.0.0';
            }
        };
    }
}
