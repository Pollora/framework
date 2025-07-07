<?php

declare(strict_types=1);

namespace Pollora\Theme\Infrastructure\Services;

use Pollora\Theme\Domain\Contracts\ThemeModuleInterface;
use Pollora\Theme\Domain\Exceptions\ThemeException;
use Pollora\Theme\Domain\Models\LaravelThemeModule;
use Pollora\Theme\Domain\Models\ThemeModule;

class WordPressThemeParser
{
    /**
     * Parse WordPress theme headers from style.css file.
     */
    public function parseThemeHeaders(string $styleCssPath): array
    {
        if (! file_exists($styleCssPath)) {
            throw ThemeException::missingRequiredFiles(basename(dirname($styleCssPath)), ['style.css']);
        }

        $contents = file_get_contents($styleCssPath);

        if ($contents === false) {
            return [];
        }

        // Extract theme headers from the CSS comment block
        if (preg_match('/\/\*[\s\S]*?\*\//m', $contents, $matches)) {
            $headerBlock = $matches[0];

            return $this->parseHeaderBlock($headerBlock);
        }

        return [];
    }

    /**
     * Create a ThemeModule from a directory path.
     */
    public function createThemeFromDirectory(string $name, string $path): ThemeModuleInterface
    {
        $styleCssPath = $path.'/style.css';
        $headers = $this->parseThemeHeaders($styleCssPath);

        // Try to create Laravel-enhanced theme module if container is available
        if (function_exists('app') && app()->bound('app')) {
            $theme = new LaravelThemeModule($name, $path, app());
        } else {
            $theme = new ThemeModule($name, $path);
        }

        $theme->setHeaders($headers);

        // With the new self-registration system, themes are enabled when they register themselves
        // This method is now primarily used for parsing theme metadata
        $theme->setEnabled(false); // Will be set to true when theme registers itself

        return $theme;
    }

    /**
     * Get theme information similar to WordPress wp_get_theme().
     */
    public function getThemeInfo(string $themePath): array
    {
        $styleCssPath = $themePath.'/style.css';
        $headers = $this->parseThemeHeaders($styleCssPath);

        return [
            'Name' => $headers['Theme Name'] ?? basename($themePath),
            'Description' => $headers['Description'] ?? '',
            'Author' => $headers['Author'] ?? '',
            'Version' => $headers['Version'] ?? '1.0.0',
            'ThemeURI' => $headers['Theme URI'] ?? null,
            'AuthorURI' => $headers['Author URI'] ?? null,
            'Template' => $headers['Template'] ?? null,
            'Status' => $headers['Status'] ?? '',
            'Tags' => $headers['Tags'] ?? '',
            'TextDomain' => $headers['Text Domain'] ?? basename($themePath),
            'DomainPath' => $headers['Domain Path'] ?? '/languages',
        ];
    }

    /**
     * Parse header block from CSS comment.
     */
    protected function parseHeaderBlock(string $headerBlock): array
    {
        $headers = [];

        // Remove comment markers
        $content = preg_replace('/^\/\*\s*|\s*\*\/$/', '', $headerBlock);
        $content = preg_replace('/^\s*\*\s?/m', '', $content);

        // Split into lines and parse each header
        $lines = explode("\n", $content);

        foreach ($lines as $line) {
            $line = trim($line);

            if (empty($line)) {
                continue;
            }

            if (strpos($line, ':') !== false) {
                [$key, $value] = explode(':', $line, 2);
                $headers[trim($key)] = trim($value);
            }
        }

        return $headers;
    }

    /**
     * Check if theme is a child theme.
     */
    public function isChildTheme(array $headers): bool
    {
        return ! empty($headers['Template']);
    }

    /**
     * Get parent theme name for child themes.
     */
    public function getParentTheme(array $headers): ?string
    {
        return $headers['Template'] ?? null;
    }

    /**
     * Normalize theme headers to standardized format.
     */
    public function normalizeHeaders(array $headers): array
    {
        $normalized = [];

        // Mapping of possible header names to standardized names
        $headerMap = [
            'Theme Name' => 'Name',
            'theme name' => 'Name',
            'name' => 'Name',
            'Description' => 'Description',
            'description' => 'Description',
            'Author' => 'Author',
            'author' => 'Author',
            'Version' => 'Version',
            'version' => 'Version',
            'Theme URI' => 'ThemeURI',
            'theme uri' => 'ThemeURI',
            'Author URI' => 'AuthorURI',
            'author uri' => 'AuthorURI',
            'Template' => 'Template',
            'template' => 'Template',
            'Text Domain' => 'TextDomain',
            'text domain' => 'TextDomain',
            'Domain Path' => 'DomainPath',
            'domain path' => 'DomainPath',
        ];

        foreach ($headers as $key => $value) {
            $normalizedKey = $headerMap[$key] ?? $key;
            $normalized[$normalizedKey] = $value;
        }

        return $normalized;
    }
}
