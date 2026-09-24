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
    /**
     * Container key of the class loader shared by every module autoloader.
     */
    public const CLASS_LOADER = 'pollora.modules.class_loader';

    protected ClassLoader $loader;

    protected array $registeredNamespaces = [];

    public function __construct(
        protected Container $app
    ) {
        $this->loader = $this->getModuleLoader();
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
        $this->registerModule($module);
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

        return $prefix.'\\'.$moduleName.'\\';
    }

    /**
     * Get the source path for a module (app/ or src/ directory).
     */
    protected function getModuleSourcePath(ModuleInterface $module): ?string
    {
        $basePath = $module->getPath();

        // Check for app/ directory (Laravel-style)
        $appPath = $basePath.'/app';
        if (is_dir($appPath)) {
            return $appPath;
        }

        // Check for src/ directory
        $srcPath = $basePath.'/src';
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
        if (! isset($this->registeredNamespaces[$namespace])) {
            $this->loader->addPsr4($namespace, $path);
            $this->registeredNamespaces[$namespace] = $path;
        }
    }

    /**
     * Register the loader with SPL, after Composer's own loaders.
     *
     * A loader that is already listed in ClassLoader::getRegisteredLoaders()
     * is left alone: namespaces added through addPsr4() take effect
     * immediately on an active loader, and registering it again would only
     * move it to the end of that list, behind any plugin loader (Query
     * Monitor, for instance), where Application::inferBasePath() would then
     * resolve the plugin's directory as the application base path.
     */
    public function register(): void
    {
        if (in_array($this->loader, ClassLoader::getRegisteredLoaders(), true)) {
            return;
        }

        $this->loader->register();
    }

    /**
     * Get the class loader that maps module namespaces.
     *
     * Module namespaces live in a loader of their own, not in Composer's root
     * loader: a root loader dumped with --classmap-authoritative answers from
     * its classmap alone and never finds a theme or plugin class. This loader
     * has no vendor directory, so it stays out of
     * ClassLoader::getRegisteredLoaders() and cannot shift
     * Application::inferBasePath().
     */
    protected function getModuleLoader(): ClassLoader
    {
        if (! $this->app->bound(self::CLASS_LOADER)) {
            $this->app->instance(self::CLASS_LOADER, new ClassLoader);
        }

        return $this->app->make(self::CLASS_LOADER);
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
