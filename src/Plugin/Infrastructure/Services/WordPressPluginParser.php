<?php

declare(strict_types=1);

namespace Pollora\Plugin\Infrastructure\Services;

/**
 * WordPress plugin header parser service.
 *
 * Parses WordPress plugin headers from plugin main files to extract
 * metadata such as plugin name, description, version, author, and other
 * standard WordPress plugin headers.
 */
class WordPressPluginParser
{
    /**
     * Standard WordPress plugin headers.
     *
     * @var array
     */
    protected array $defaultHeaders = [
        'Name' => 'Plugin Name',
        'PluginURI' => 'Plugin URI',
        'Version' => 'Version',
        'Description' => 'Description',
        'Author' => 'Author',
        'AuthorURI' => 'Author URI',
        'TextDomain' => 'Text Domain',
        'DomainPath' => 'Domain Path',
        'RequiresWP' => 'Requires at least',
        'TestedUpTo' => 'Tested up to',
        'RequiresPHP' => 'Requires PHP',
        'Network' => 'Network',
        'License' => 'License',
        'LicenseURI' => 'License URI',
        'UpdateURI' => 'Update URI',
    ];

    /**
     * Parse plugin headers from a plugin main file.
     *
     * @param string $pluginFile Path to the plugin main file
     * @return array Parsed plugin headers
     */
    public function parsePluginHeaders(string $pluginFile): array
    {
        if (! file_exists($pluginFile)) {
            return [];
        }

        $pluginData = $this->getFileData($pluginFile, $this->defaultHeaders);

        // Clean up boolean values
        $pluginData['Network'] = $this->parseBooleanValue($pluginData['Network'] ?? '');

        // Clean up empty values
        $pluginData = array_filter($pluginData, function ($value): bool {
            return $value !== '' && $value !== null;
        });

        return $pluginData;
    }

    /**
     * Parse plugin headers with custom headers.
     *
     * @param string $pluginFile Path to the plugin main file
     * @param array $extraHeaders Additional headers to parse
     * @return array Parsed plugin headers including custom headers
     */
    public function parsePluginHeadersWithExtra(string $pluginFile, array $extraHeaders = []): array
    {
        $headers = array_merge($this->defaultHeaders, $extraHeaders);
        
        if (! file_exists($pluginFile)) {
            return [];
        }

        $pluginData = $this->getFileData($pluginFile, $headers);

        // Clean up boolean values
        $pluginData['Network'] = $this->parseBooleanValue($pluginData['Network'] ?? '');

        // Clean up empty values
        $pluginData = array_filter($pluginData, function ($value): bool {
            return $value !== '' && $value !== null;
        });

        return $pluginData;
    }

    /**
     * Get file data (similar to WordPress get_file_data function).
     *
     * @param string $file Path to the file
     * @param array $defaultHeaders Headers to extract
     * @param string $context Context for the operation
     * @return array Extracted file data
     */
    protected function getFileData(string $file, array $defaultHeaders, string $context = ''): array
    {
        // Read the first 8 KB of the file for headers
        $fp = fopen($file, 'r');
        if (! $fp) {
            return [];
        }

        $fileData = fread($fp, 8192);
        fclose($fp);

        // Make sure we catch CR-only line endings
        $fileData = str_replace("\r", "\n", $fileData);

        $headers = [];

        foreach ($defaultHeaders as $field => $regex) {
            if (preg_match('/^[ \t\/*#@]*'.preg_quote($regex, '/').':(.*)$/mi', $fileData, $match) && $match[1]) {
                $headers[$field] = $this->cleanupHeaderValue($match[1]);
            } else {
                $headers[$field] = '';
            }
        }

        return $headers;
    }

    /**
     * Clean up header value.
     *
     * @param string $value Header value to clean
     * @return string Cleaned header value
     */
    protected function cleanupHeaderValue(string $value): string
    {
        return trim(preg_replace('/\s*(?:\*\/|\?>).*/', '', $value));
    }

    /**
     * Parse boolean value from string.
     *
     * @param string $value String value to parse
     * @return bool Parsed boolean value
     */
    protected function parseBooleanValue(string $value): bool
    {
        $value = strtolower(trim($value));
        
        return in_array($value, ['true', '1', 'yes', 'on'], true);
    }

    /**
     * Validate plugin main file structure.
     *
     * @param string $pluginFile Path to the plugin main file
     * @return array Validation result with errors if any
     */
    public function validatePluginFile(string $pluginFile): array
    {
        $errors = [];

        if (! file_exists($pluginFile)) {
            $errors[] = 'Plugin main file does not exist';
            return [
                'valid' => false,
                'errors' => $errors,
            ];
        }

        // Check if file is readable
        if (! is_readable($pluginFile)) {
            $errors[] = 'Plugin main file is not readable';
        }

        // Parse headers and check for required ones
        $headers = $this->parsePluginHeaders($pluginFile);
        
        if (empty($headers['Name'])) {
            $errors[] = 'Plugin Name header is missing';
        }

        if (empty($headers['Version'])) {
            $errors[] = 'Version header is missing';
        }

        // Check for PHP opening tag
        $content = file_get_contents($pluginFile);
        if (! str_starts_with($content, '<?php')) {
            $errors[] = 'Plugin file must start with <?php tag';
        }

        // Check for security header to prevent direct access
        if (! str_contains($content, 'ABSPATH') && ! str_contains($content, 'defined')) {
            $errors[] = 'Plugin should include security check to prevent direct access';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'headers' => $headers,
        ];
    }

    /**
     * Extract plugin slug from plugin file path.
     *
     * @param string $pluginFile Path to the plugin main file
     * @return string Plugin slug
     */
    public function extractPluginSlug(string $pluginFile): string
    {
        $dirname = dirname($pluginFile);
        $pluginDir = basename($dirname);
        
        // If plugin is in a subdirectory, use that as the slug
        if ($pluginDir !== '.' && $pluginDir !== '') {
            return $pluginDir;
        }
        
        // Otherwise, use filename without extension
        return basename($pluginFile, '.php');
    }

    /**
     * Extract plugin basename from plugin file path.
     *
     * @param string $pluginFile Path to the plugin main file
     * @return string Plugin basename (directory/file.php)
     */
    public function extractPluginBasename(string $pluginFile): string
    {
        $dirname = dirname($pluginFile);
        $pluginDir = basename($dirname);
        $fileName = basename($pluginFile);
        
        return $pluginDir.'/'.$fileName;
    }

    /**
     * Get default plugin headers structure.
     *
     * @return array Default plugin headers
     */
    public function getDefaultHeaders(): array
    {
        return $this->defaultHeaders;
    }

    /**
     * Check if file contains valid plugin headers.
     *
     * @param string $pluginFile Path to the plugin main file
     * @return bool True if file contains valid plugin headers
     */
    public function hasValidPluginHeaders(string $pluginFile): bool
    {
        $headers = $this->parsePluginHeaders($pluginFile);
        
        return ! empty($headers['Name']);
    }

    /**
     * Parse version from plugin headers.
     *
     * @param string $pluginFile Path to the plugin main file
     * @return string|null Plugin version or null if not found
     */
    public function parseVersion(string $pluginFile): ?string
    {
        $headers = $this->parsePluginHeaders($pluginFile);
        
        return $headers['Version'] ?? null;
    }

    /**
     * Parse text domain from plugin headers.
     *
     * @param string $pluginFile Path to the plugin main file
     * @return string|null Plugin text domain or null if not found
     */
    public function parseTextDomain(string $pluginFile): ?string
    {
        $headers = $this->parsePluginHeaders($pluginFile);
        
        return $headers['TextDomain'] ?? null;
    }

    /**
     * Parse required WordPress version from plugin headers.
     *
     * @param string $pluginFile Path to the plugin main file
     * @return string|null Required WordPress version or null if not found
     */
    public function parseRequiredWpVersion(string $pluginFile): ?string
    {
        $headers = $this->parsePluginHeaders($pluginFile);
        
        return $headers['RequiresWP'] ?? null;
    }

    /**
     * Parse required PHP version from plugin headers.
     *
     * @param string $pluginFile Path to the plugin main file
     * @return string|null Required PHP version or null if not found
     */
    public function parseRequiredPhpVersion(string $pluginFile): ?string
    {
        $headers = $this->parsePluginHeaders($pluginFile);
        
        return $headers['RequiresPHP'] ?? null;
    }
}