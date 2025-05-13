<?php

declare(strict_types=1);

namespace Pollora\Theme\Domain\Models;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Represents metadata for a WordPress theme
 */
class ThemeMetadata
{
    protected ?string $parentTheme = null;

    protected array $config = [];

    /**
     * Create a new ThemeMetadata instance
     */
    public function __construct(
        protected string $name,
        protected string $basePath
    ) {}

    /**
     * Get the theme name
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the theme's base path
     */
    public function getBasePath(): string
    {
        return $this->basePath.'/'.$this->name;
    }

    /**
     * Get the theme's view path
     */
    public function getViewPath(): string
    {
        return $this->getBasePath().'/views';
    }

    /**
     * Get the theme's configuration path
     */
    public function getConfigPath(): string
    {
        return $this->getBasePath().'/theme.json';
    }

    /**
     * Get the theme's language path
     */
    public function getLanguagePath(): string
    {
        return $this->getBasePath().'/lang';
    }

    /**
     * Get the theme's includes directory
     */
    public function getThemeIncDir(): string
    {
        return $this->getBasePath().'/inc';
    }

    /**
     * Get the parent theme name
     */
    public function getParentTheme(): ?string
    {
        return $this->parentTheme;
    }

    /**
     * Load theme configuration
     */
    public function loadConfiguration(): void
    {
        $configPath = $this->getConfigPath();

        if (file_exists($configPath)) {
            $config = json_decode(file_get_contents($configPath), true);

            if (isset($config['parent'])) {
                $this->parentTheme = $config['parent'];
            }

            $this->config = $config;
        }
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
     * @param  string  $subDirectory  (Optional) A subdirectory within the theme's app directory.
     * @return string The full path to the theme's app directory.
     */
    public function getThemeAppDir(string $subDirectory = ''): string
    {
        return rtrim(app_path('Themes'.DIRECTORY_SEPARATOR.$this->getThemeNamespace()).DIRECTORY_SEPARATOR.$subDirectory, DIRECTORY_SEPARATOR);
    }

    /**
     * Get the full path to a theme application file.
     *
     * @param  string  $file  The file name.
     * @return string The full file path.
     */
    public function getThemeAppFile(string $file): string
    {
        return $this->getThemeAppDir().DIRECTORY_SEPARATOR.$file;
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
     * Get the path for a specific item within the theme.
     *
     * @param  string|array|null  $pathParts  A single path segment or an array of segments.
     * @return string The resolved full path.
     */
    public function getPathForItem(string|array|null $pathParts = null): string
    {
        $pathParts = Arr::wrap($pathParts);
        $folders = empty($pathParts) ? '' : implode('/', $pathParts);

        return $this->getBasePath().'/'.$folders;
    }
}
