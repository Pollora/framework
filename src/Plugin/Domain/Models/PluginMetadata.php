<?php

declare(strict_types=1);

namespace Pollora\Plugin\Domain\Models;

/**
 * Represents metadata for a WordPress plugin.
 *
 * This class handles plugin metadata including paths, configuration,
 * and namespace management for PSR-4 autoloading.
 */
class PluginMetadata
{
    /**
     * Plugin configuration array.
     *
     * @var array
     */
    protected array $config = [];

    /**
     * Create a new PluginMetadata instance.
     *
     * @param string $name Plugin name
     * @param string $basePath Base path where plugins are located
     */
    public function __construct(
        protected string $name,
        protected string $basePath
    ) {}

    /**
     * Get the plugin name.
     *
     * @return string Plugin name
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the plugin's base path.
     *
     * @return string Full path to the plugin directory
     */
    public function getBasePath(): string
    {
        return $this->basePath.'/'.$this->name;
    }

    /**
     * Get the plugin's main file path.
     *
     * @return string Path to the main plugin file
     */
    public function getMainFilePath(): string
    {
        return $this->getBasePath().'/'.$this->name.'.php';
    }

    /**
     * Get the plugin's configuration path.
     *
     * @return string Path to the plugin configuration file
     */
    public function getConfigPath(): string
    {
        return $this->getBasePath().'/plugin.json';
    }

    /**
     * Get the plugin's language path.
     *
     * @return string Path to the plugin language directory
     */
    public function getLanguagePath(): string
    {
        return $this->getBasePath().'/languages';
    }

    /**
     * Get the plugin's includes directory.
     *
     * @return string Path to the plugin includes directory
     */
    public function getPluginIncDir(): string
    {
        return $this->getPluginAppDir().'/inc';
    }

    /**
     * Load plugin configuration.
     *
     * @return void
     */
    public function loadConfiguration(): void
    {
        $configPath = $this->getConfigPath();

        if (file_exists($configPath)) {
            $config = json_decode(file_get_contents($configPath), true);
            $this->config = $config ?? [];
        }
    }

    /**
     * Get the plugin's namespace in StudlyCase format.
     *
     * @return string The formatted plugin namespace
     */
    public function getPluginNamespace(): string
    {
        return $this->studlify($this->getName());
    }

    /**
     * Get the plugin's application directory.
     *
     * @param string $subDirectory Optional subdirectory within the plugin's app directory
     * @return string The full path to the plugin's app directory
     */
    public function getPluginAppDir(string $subDirectory = ''): string
    {
        return rtrim($this->getBasePath().DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.$subDirectory, DIRECTORY_SEPARATOR);
    }

    /**
     * Get the full path to a plugin application file.
     *
     * @param string $file The file name
     * @return string The full file path
     */
    public function getPluginAppFile(string $file): string
    {
        return $this->getPluginAppDir().DIRECTORY_SEPARATOR.$file;
    }

    /**
     * Get the plugin configuration.
     *
     * @return array The configuration array
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Get the path for a specific item within the plugin.
     *
     * @param string|array|null $pathParts A single path segment or an array of segments
     * @return string The resolved full path
     */
    public function getPathForItem(string|array|null $pathParts = null): string
    {
        $pathParts = $this->wrapInArray($pathParts);
        $folders = $pathParts === [] ? '' : implode('/', $pathParts);

        return $this->getBasePath().'/'.$folders;
    }

    /**
     * Get the plugin's views directory.
     *
     * @return string Path to the plugin views directory
     */
    public function getViewsPath(): string
    {
        return $this->getBasePath().'/views';
    }

    /**
     * Get the plugin's assets directory.
     *
     * @return string Path to the plugin assets directory
     */
    public function getAssetsPath(): string
    {
        return $this->getBasePath().'/assets';
    }

    /**
     * Get the plugin's routes directory.
     *
     * @return string Path to the plugin routes directory
     */
    public function getRoutesPath(): string
    {
        return $this->getBasePath().'/routes';
    }

    /**
     * Get the plugin's config directory.
     *
     * @return string Path to the plugin config directory
     */
    public function getConfigDir(): string
    {
        return $this->getBasePath().'/config';
    }

    /**
     * Get the plugin's database directory.
     *
     * @return string Path to the plugin database directory
     */
    public function getDatabasePath(): string
    {
        return $this->getBasePath().'/database';
    }

    /**
     * Get the plugin's tests directory.
     *
     * @return string Path to the plugin tests directory
     */
    public function getTestsPath(): string
    {
        return $this->getBasePath().'/tests';
    }

    /**
     * Get the plugin slug (lowercase, hyphenated).
     *
     * @return string Plugin slug
     */
    public function getSlug(): string
    {
        return strtolower(str_replace(['_', ' '], '-', $this->name));
    }

    /**
     * Get the plugin basename (directory/main-file.php).
     *
     * @return string Plugin basename
     */
    public function getBasename(): string
    {
        return $this->name.'/'.$this->name.'.php';
    }

    /**
     * Get the application base path - abstracted to remove framework dependency.
     *
     * @return string Application base path
     */
    protected function getApplicationPath(): string
    {
        if (function_exists('app_path')) {
            return app_path();
        }

        // Fallback to a reasonable default when outside framework
        return getcwd().'/app';
    }

    /**
     * Convert a string to StudlyCase format.
     *
     * @param string $value Input string
     * @return string StudlyCase formatted string
     */
    protected function studlify(string $value): string
    {
        $value = ucwords(str_replace(['-', '_'], ' ', $value));

        return str_replace(' ', '', $value);
    }

    /**
     * Wrap a value in an array if it's not already an array.
     *
     * @param mixed $value Value to wrap
     * @return array Wrapped value
     */
    protected function wrapInArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        return $value === null ? [] : [$value];
    }
}