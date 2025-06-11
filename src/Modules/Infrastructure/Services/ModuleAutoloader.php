<?php

declare(strict_types=1);

namespace Pollora\Modules\Infrastructure\Services;

use Composer\Autoload\ClassLoader;
use Illuminate\Container\Container;
use Pollora\Modules\Domain\Contracts\ModuleInterface;

/**
 * Generic module autoloader service for dynamic PSR-4 autoloading.
 *
 * This service handles dynamic autoloading for modules (themes, plugins)
 * using fixed namespace conventions:
 * - Themes: Theme\{ThemeName}\
 * - Plugins: Plugin\{PluginName}\
 *
 * The autoloader automatically maps these namespaces to the module's
 * source directory (app/ or src/) following PSR-4 standards.
 *
 * Example:
 * - Theme "Solidarmonde" at /themes/solidarmonde/app
 * - Namespace: Theme\Solidarmonde\
 * - Class: Theme\Solidarmonde\Providers\ThemeServiceProvider
 * - File: /themes/solidarmonde/app/Providers/ThemeServiceProvider.php
 */
class ModuleAutoloader
{
    protected ClassLoader $loader;
    protected array $registeredNamespaces = [];

    public function __construct(
        protected Container $app
    ) {
        $this->loader = $this->getComposerLoader();
    }

    /**
     * Register autoloading for a module with fixed namespace convention.
     */
    public function registerModule(ModuleInterface $module, string $type = 'theme'): void
    {
        $namespace = $this->buildNamespace($module->getStudlyName(), $type);
        $path = $this->getModuleSourcePath($module);

        if ($path && is_dir($path)) {
            $this->addPsr4Namespace($namespace, $path);
        }
    }

    /**
     * Register autoloading for a theme.
     */
    public function registerTheme(ModuleInterface $module): void
    {
        $this->registerModule($module, 'theme');
    }

    /**
     * Register autoloading for a plugin.
     */
    public function registerPlugin(ModuleInterface $module): void
    {
        $this->registerModule($module, 'plugin');
    }

    /**
     * Build namespace following our fixed conventions.
     */
    protected function buildNamespace(string $moduleName, string $type): string
    {
        $prefix = ucfirst(strtolower($type)); // Theme or Plugin
        return $prefix . '\\' . $moduleName . '\\';
    }

    /**
     * Get the source path for a module (app/ or src/ directory).
     */
    protected function getModuleSourcePath(ModuleInterface $module): ?string
    {
        $basePath = $module->getPath();

        // Check for app/ directory (Laravel-style)
        $appPath = $basePath . '/app';
        if (is_dir($appPath)) {
            return $appPath;
        }

        // Check for src/ directory
        $srcPath = $basePath . '/src';
        if (is_dir($srcPath)) {
            return $srcPath;
        }

        return null;
    }

    /**
     * Add a PSR-4 namespace to the autoloader.
     */
    protected function addPsr4Namespace(string $namespace, string $path): void
    {
        if (!isset($this->registeredNamespaces[$namespace])) {
            $this->loader->addPsr4($namespace, $path);
            $this->registeredNamespaces[$namespace] = $path;
        }
    }

    /**
     * Register all namespaces with Composer's autoloader.
     */
    public function register(): void
    {
        $this->loader->register();
    }

    /**
     * Get the Composer ClassLoader instance.
     */
    protected function getComposerLoader(): ClassLoader
    {
        // Try to get from the app container first
        if ($this->app->bound(ClassLoader::class)) {
            return $this->app->make(ClassLoader::class);
        }

        // Fallback: find Composer's autoloader from global functions
        $autoloadFunctions = spl_autoload_functions();

        foreach ($autoloadFunctions as $function) {
            if (is_array($function) &&
                isset($function[0]) &&
                $function[0] instanceof ClassLoader
            ) {
                // Bind it to the container for future use
                $this->app->instance(ClassLoader::class, $function[0]);
                return $function[0];
            }
        }

        // Last resort: create a new instance (not recommended in production)
        $loader = new ClassLoader();
        $this->app->instance(ClassLoader::class, $loader);
        return $loader;
    }

    /**
     * Get all registered namespaces.
     */
    public function getRegisteredNamespaces(): array
    {
        return $this->registeredNamespaces;
    }

    /**
     * Check if a namespace is registered.
     */
    public function isNamespaceRegistered(string $namespace): bool
    {
        return isset($this->registeredNamespaces[$namespace]);
    }

    /**
     * Unregister a namespace (for testing purposes).
     */
    public function unregisterNamespace(string $namespace): void
    {
        unset($this->registeredNamespaces[$namespace]);

        // Note: We can't easily remove from ClassLoader,
        // but this helps with tracking what we've registered
    }
}
