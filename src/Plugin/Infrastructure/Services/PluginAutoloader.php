<?php

declare(strict_types=1);

namespace Pollora\Plugin\Infrastructure\Services;

use Pollora\Modules\Infrastructure\Services\ModuleAutoloader;
use Pollora\Plugin\Domain\Models\PluginModule;

/**
 * Plugin autoloader service.
 *
 * Handles autoloading for plugins using the fixed namespace convention:
 * Plugin\{PluginName}\
 * 
 * This service extends the generic ModuleAutoloader to provide plugin-specific
 * autoloading functionality, mapping plugin namespaces to their source directories
 * following PSR-4 standards.
 */
class PluginAutoloader extends ModuleAutoloader
{
    /**
     * Register autoloading for a plugin module.
     *
     * @param PluginModule $plugin Plugin module to register
     * @return void
     */
    public function registerPluginModule(PluginModule $plugin): void
    {
        $this->registerPlugin($plugin);
        $this->register();
    }

    /**
     * Register autoloading for multiple plugins.
     *
     * @param array $plugins Array of plugin modules
     * @return void
     */
    public function registerPlugins(array $plugins): void
    {
        foreach ($plugins as $plugin) {
            if ($plugin instanceof PluginModule) {
                $this->registerPlugin($plugin);
            }
        }

        $this->register();
    }

    /**
     * Get the expected namespace for a plugin.
     *
     * @param string $pluginName Plugin name
     * @return string Expected namespace
     */
    public function getPluginNamespace(string $pluginName): string
    {
        return $this->buildNamespace($pluginName, 'plugin');
    }

    /**
     * Check if a plugin namespace is registered.
     *
     * @param string $pluginName Plugin name
     * @return bool True if namespace is registered
     */
    public function isPluginRegistered(string $pluginName): bool
    {
        $namespace = $this->getPluginNamespace($pluginName);

        return $this->isNamespaceRegistered($namespace);
    }

    /**
     * Get all registered plugin namespaces.
     *
     * @return array Array of plugin namespaces
     */
    public function getRegisteredPluginNamespaces(): array
    {
        return array_filter(
            $this->getRegisteredNamespaces(),
            fn (string $namespace): bool => str_starts_with($namespace, 'Plugin\\'),
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * Unregister a plugin namespace.
     *
     * @param string $pluginName Plugin name
     * @return void
     */
    public function unregisterPlugin(string $pluginName): void
    {
        $namespace = $this->getPluginNamespace($pluginName);
        $this->unregisterNamespace($namespace);
    }

    /**
     * Register autoloading for a specific plugin by name and path.
     *
     * @param string $pluginName Plugin name
     * @param string $pluginPath Plugin path
     * @return bool True if registration was successful
     */
    public function registerPluginByPath(string $pluginName, string $pluginPath): bool
    {
        $namespace = $this->getPluginNamespace($pluginName);
        
        // Check for app/ directory (Laravel-style)
        $appPath = rtrim($pluginPath, '/').'/app';
        if (is_dir($appPath)) {
            $this->addPsr4Namespace($namespace, $appPath);
            return true;
        }

        // Check for src/ directory
        $srcPath = rtrim($pluginPath, '/').'/src';
        if (is_dir($srcPath)) {
            $this->addPsr4Namespace($namespace, $srcPath);
            return true;
        }

        return false;
    }

    /**
     * Get plugin source path by plugin name.
     *
     * @param string $pluginName Plugin name
     * @return string|null Plugin source path or null if not found
     */
    public function getPluginSourcePath(string $pluginName): ?string
    {
        $namespace = $this->getPluginNamespace($pluginName);
        
        return $this->registeredNamespaces[$namespace] ?? null;
    }

    /**
     * Check if plugin has autoloadable source directory.
     *
     * @param string $pluginPath Plugin path
     * @return bool True if plugin has autoloadable source directory
     */
    public function hasAutoloadableSource(string $pluginPath): bool
    {
        $pluginPath = rtrim($pluginPath, '/');
        
        return is_dir($pluginPath.'/app') || is_dir($pluginPath.'/src');
    }

    /**
     * Get the autoloadable source directory for a plugin path.
     *
     * @param string $pluginPath Plugin path
     * @return string|null Source directory path or null if not found
     */
    public function getAutoloadableSourcePath(string $pluginPath): ?string
    {
        $pluginPath = rtrim($pluginPath, '/');
        
        // Prefer app/ directory
        if (is_dir($pluginPath.'/app')) {
            return $pluginPath.'/app';
        }
        
        // Fallback to src/ directory
        if (is_dir($pluginPath.'/src')) {
            return $pluginPath.'/src';
        }
        
        return null;
    }
}