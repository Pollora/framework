<?php

declare(strict_types=1);

namespace Pollora\Foundation\Console\Commands\Concerns;

use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Trait to resolve the target location for generated files.
 * This trait determines where files should be generated based on command options.
 */
trait ResolvesLocation
{
    use HasPathSupport, HasPluginSupport, HasThemeSupport, HasModuleSupport;

    /**
     * Resolve the target location for the generated file.
     *
     * @return array{type: string, path: string, namespace: string, source_path: string, source_namespace: string}
     */
    protected function resolveTargetLocation(): array
    {
        // Check for module first
        if ($this->isGeneratingInModule()) {
            return $this->getModuleConfig();
        }

        // Then check for theme
        if ($this->isGeneratingInTheme()) {
            return $this->getThemeConfig();
        }

        // Then check for plugin
        if ($this->isGeneratingInPlugin()) {
            return $this->getPluginConfig();
        }

        // Finally, check for custom path
        if ($this->isGeneratingInCustomPath()) {
            return $this->getCustomPathConfig();
        }

        // Default to app
        return [
            'type' => 'app',
            'path' => app_path(),
            'namespace' => app()->getNamespace(),
            'source_path' => app_path(),
            'source_namespace' => app()->getNamespace(),
        ];
    }

    /**
     * Get the resolved file path.
     *
     * @param  array{type: string, path: string, namespace: string, source_path: string, source_namespace: string}  $location
     */
    protected function getResolvedFilePath(array $location, string $className, string $subPath = ''): string
    {
        $path = $location['source_path'];

        if ($subPath !== '') {
            $path .= '/'.$subPath;
        }

        return $path.'/'.$className.'.php';
    }

    /**
     * Get the resolved namespace.
     *
     * @param  array{type: string, path: string, namespace: string, source_path: string, source_namespace: string}  $location
     */
    protected function getResolvedNamespace(array $location, string $subPath = ''): string
    {
        $namespace = $location['source_namespace'];

        if ($subPath !== '') {
            $namespace .= str_replace('/', '\\', $subPath);
        }

        return $namespace;
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

        if (! is_dir($themePath)) {
            throw new InvalidArgumentException("Theme directory not found: {$themePath}");
        }

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

        return rtrim($themesPath, '/') . 'ResolvesLocation.php/' .$themeName;
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
}
