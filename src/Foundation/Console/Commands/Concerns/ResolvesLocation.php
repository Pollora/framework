<?php

declare(strict_types=1);

namespace Pollora\Foundation\Console\Commands\Concerns;

/**
 * Trait to resolve the target location for generated files.
 * This trait determines where files should be generated based on command options.
 */
trait ResolvesLocation
{
    use HasModuleSupport, HasPathSupport, HasPluginSupport, HasThemeSupport;

    /**
     * Resolve the target location for the generated file.
     *
     * @return array{type: string, path: string, namespace: string, source_path: string, source_namespace: string}
     */
    protected function resolveTargetLocation(): array
    {
        // Check for module first
        if ($this->hasModuleOption()) {
            return $this->resolveModuleLocation();
        }

        // Then check for theme
        if ($this->hasThemeOption()) {
            return $this->resolveThemeLocation();
        }

        // Then check for plugin
        if ($this->hasPluginOption()) {
            return $this->resolvePluginLocation();
        }

        // Finally, check for custom path
        if ($this->hasPathOption()) {
            return $this->resolveCustomPath();
        }

        // Default to app
        return $this->resolveDefaultLocation();
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
     * Resolve default app location.
     *
     * @return array{type: string, path: string, namespace: string}
     */
    protected function resolveDefaultLocation(): array
    {
        return [
            'type' => 'app',
            'path' => app_path(),
            'namespace' => app()->getNamespace(),
            'source_path' => app_path(),
            'source_namespace' => app()->getNamespace(),
        ];
    }
}
