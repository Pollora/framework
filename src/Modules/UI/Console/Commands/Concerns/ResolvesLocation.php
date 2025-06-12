<?php

declare(strict_types=1);

namespace Pollora\Modules\UI\Console\Commands\Concerns;

use InvalidArgumentException;

trait ResolvesLocation
{
    /**
     * Resolve the target location for file generation.
     * Priority: custom path > plugin > theme > default app path
     * 
     * @return array{type: string, path: string, namespace: string, name?: string}
     * @throws InvalidArgumentException When location parameters are invalid
     */
    protected function resolveTargetLocation(): array
    {
        // Priority: custom path > plugin > theme > default app path

        if ($this->hasPathOption()) {
            return $this->resolveCustomPath();
        }

        if ($this->hasPluginOption()) {
            return $this->resolvePluginLocation();
        }

        if ($this->hasThemeOption()) {
            return $this->resolveThemeLocation();
        }

        // Default to app path
        return $this->resolveDefaultLocation();
    }

    /**
     * Resolve custom path location.
     * 
     * @return array{type: string, path: string, namespace: string}
     * @throws InvalidArgumentException When custom path is empty
     */
    protected function resolveCustomPath(): array
    {
        $path = $this->resolvePath();

        if (! $path) {
            throw new InvalidArgumentException('Custom path cannot be empty when --path option is used.');
        }

        // Ensure absolute path
        if (! str_starts_with($path, '/')) {
            $path = base_path($path);
        }

        return [
            'type' => 'custom',
            'path' => $path,
            'namespace' => 'App', // Default namespace for custom paths
        ];
    }

    /**
     * Resolve plugin location.
     * 
     * @return array{type: string, path: string, namespace: string, name: string}
     * @throws InvalidArgumentException When plugin is not found or support not implemented
     */
    protected function resolvePluginLocation(): array
    {
        $plugin = $this->resolvePlugin();

        if (! $plugin) {
            throw new InvalidArgumentException('Plugin name cannot be empty when --plugin option is used.');
        }

        // Plugin paths would be resolved by plugin system
        // For now, throw exception as plugin system is not implemented yet
        throw new InvalidArgumentException('Plugin support is not yet implemented.');
    }

    /**
     * Resolve theme location.
     * 
     * @return array{type: string, path: string, namespace: string, name: string}
     * @throws InvalidArgumentException When theme is not found
     */
    protected function resolveThemeLocation(): array
    {
        $theme = $this->resolveTheme();

        if (! $theme) {
            $theme = $this->getActiveTheme();
        }

        if (! $theme) {
            throw new InvalidArgumentException('No theme specified and no active theme found.');
        }

        // Get theme path from theme system
        $themePath = $this->getThemePath($theme);

        return [
            'type' => 'theme',
            'name' => $theme,
            'path' => $themePath,
            'namespace' => 'Theme\\'.$this->normalizeThemeName($theme),
        ];
    }

    /**
     * Resolve default app location.
     * 
     * @return array{type: string, path: string, namespace: string}
     */
    protected function resolveDefaultLocation(): array
    {
        return [
            'type' => 'app',
            'path' => app_path(),
            'namespace' => 'App',
        ];
    }

    /**
     * Get theme path for a given theme name.
     * 
     * @param string $themeName The theme name
     * @return string The theme path
     */
    protected function getThemePath(string $themeName): string
    {
        // Default themes path, can be overridden
        $themesPath = config('theme.path', base_path('themes'));

        return rtrim($themesPath, '/').'/'.$themeName;
    }

    /**
     * Normalize theme name for namespace.
     * 
     * @param string $themeName The theme name to normalize
     * @return string The normalized theme name for namespace
     */
    protected function normalizeThemeName(string $themeName): string
    {
        return str_replace(['-', '_', ' '], '', ucwords($themeName, '-_ '));
    }

    /**
     * Get the resolved file path based on location and class type.
     * 
     * @param array $location The location information
     * @param string $className The class name
     * @param string $subPath Optional subdirectory path
     * @return string The complete file path
     */
    protected function getResolvedFilePath(array $location, string $className, string $subPath = ''): string
    {
        $basePath = $location['path'];

        if ($location['type'] === 'theme') {
            $basePath .= '/app';
        }

        if ($subPath) {
            $basePath .= '/'.trim($subPath, '/');
        }

        return $basePath.'/'.$className.'.php';
    }

    /**
     * Get the resolved namespace based on location and class type.
     * 
     * @param array $location The location information
     * @param string $subNamespace Optional sub-namespace
     * @return string The complete namespace
     */
    protected function getResolvedNamespace(array $location, string $subNamespace = ''): string
    {
        $namespace = $location['namespace'];

        if ($subNamespace) {
            $namespace .= '\\'.trim($subNamespace, '\\');
        }

        return $namespace;
    }
}
