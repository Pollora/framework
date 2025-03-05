<?php

declare(strict_types=1);

namespace Pollora\Theme;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Class ThemeMetadata
 *
 * Handles theme metadata and directory structure within a Laravel/WordPress package.
 */
class ThemeMetadata
{
    /** @var array The configuration settings for the theme. */
    protected array $config = [];

    /** @var string The theme's name */
    protected string $name;

    /** @var string The application path for the theme. */
    protected string $appPath;

    /**
     * ThemeMetadata constructor.
     *
     * @param string $name The original theme name.
     * @param string $basePath The base path where the theme is stored.
     */
    public function __construct(string $name, protected string $basePath)
    {
        $this->name = Str::snake(strtolower($name));
        $this->appPath = $this->getThemeAppDir();
    }

    /**
     * Get the theme's namespace in StudlyCase format.
     *
     * @return string The formatted theme namespace.
     */
    public function getThemeNamespace(): string
    {
        return Str::studly($this->getName());
    }

    /**
     * Get the theme's application directory.
     *
     * @param string $subDirectory (Optional) A subdirectory within the theme's app directory.
     * @return string The full path to the theme's app directory.
     */
    public function getThemeAppDir(string $subDirectory = ''): string
    {
        return rtrim(app_path('Themes'.DIRECTORY_SEPARATOR.$this->getThemeNamespace()).DIRECTORY_SEPARATOR.$subDirectory, DIRECTORY_SEPARATOR);
    }

    /**
     * Get the theme's "inc" directory path.
     *
     * @return string The path to the "inc" directory.
     */
    public function getThemeIncDir(): string
    {
        return $this->getThemeAppDir('inc');
    }

    /**
     * Get the full path to a theme application file.
     *
     * @param string $file The file name.
     * @return string The full file path.
     */
    public function getThemeAppFile(string $file): string
    {
        return $this->getThemeAppDir().DIRECTORY_SEPARATOR.$file;
    }

    /**
     * Get the theme name in snake case.
     *
     * @return string The theme name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the theme configuration.
     *
     * @return array The configuration array.
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Get the parent theme name, if any.
     *
     * @return string|null The parent theme name, or null if none.
     */
    public function getParentTheme(): ?string
    {
        return $this->config['parent'] ?? null;
    }

    /**
     * Get the base path for the theme.
     *
     * @return string The full base path.
     */
    public function getBasePath(): string
    {
        return "{$this->basePath}/{$this->name}";
    }

    /**
     * Get the path for a specific item within the theme.
     *
     * @param string|array|null $pathParts A single path segment or an array of segments.
     * @return string The resolved full path.
     */
    public function getPathForItem(string|array|null $pathParts = null): string
    {
        $pathParts = Arr::wrap($pathParts);
        $folders = empty($pathParts) ? '' : implode('/', $pathParts);

        return $this->getBasePath().'/'.$folders;
    }

    /**
     * Get the configuration file path for the theme.
     *
     * @return string The configuration file path.
     */
    public function getConfigPath(): string
    {
        return $this->getPathForItem('config/config.php');
    }

    /**
     * Load the theme configuration from the config file.
     *
     * @return void
     */
    public function loadConfiguration(): void
    {
        $this->config = $this->safeLoadConfig($this->getConfigPath());
    }

    /**
     * Safely load the configuration file if it exists.
     *
     * @param string $path The file path to load.
     * @return array The configuration data.
     */
    protected function safeLoadConfig(string $path): array
    {
        return file_exists($path) ? include $path : [];
    }

    /**
     * Get the path to the views directory.
     *
     * @return string The views directory path.
     */
    public function getViewPath(): string
    {
        return $this->getPathForItem('views');
    }

    /**
     * Get the path to the language files directory.
     *
     * @return string The language files directory path.
     */
    public function getLanguagePath(): string
    {
        return $this->getPathForItem('lang');
    }
}
