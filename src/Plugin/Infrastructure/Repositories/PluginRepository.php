<?php

declare(strict_types=1);

namespace Pollora\Plugin\Infrastructure\Repositories;

use Illuminate\Container\Container;
use Illuminate\Support\Collection;
use Pollora\Collection\Domain\Contracts\CollectionInterface;
use Pollora\Collection\Infrastructure\Adapters\LaravelCollectionAdapter;
use Pollora\Modules\Domain\Contracts\ModuleRepositoryInterface;
use Pollora\Plugin\Domain\Contracts\PluginModuleInterface;
use Pollora\Plugin\Domain\Models\LaravelPluginModule;
use Pollora\Plugin\Domain\Support\PluginCollection;
use Pollora\Plugin\Infrastructure\Services\WordPressPluginParser;

/**
 * Plugin repository implementation.
 *
 * Handles plugin discovery, storage, and retrieval operations.
 * Provides methods for managing plugin modules within the framework.
 */
class PluginRepository implements ModuleRepositoryInterface
{
    /**
     * Cached plugin modules.
     *
     * @var array<string, PluginModuleInterface>
     */
    protected array $plugins = [];

    /**
     * Cache status flag.
     */
    protected bool $cached = false;

    /**
     * Plugins base path.
     */
    protected string $pluginsPath;

    /**
     * Create a new PluginRepository instance.
     *
     * @param  Container  $app  Application container
     * @param  WordPressPluginParser  $parser  Plugin parser service
     */
    public function __construct(
        protected Container $app,
        protected WordPressPluginParser $parser
    ) {
        $this->pluginsPath = $this->getPluginsBasePath();
    }

    /**
     * Get all plugin modules.
     *
     * @return array<string, PluginModuleInterface> All plugin modules
     */
    public function all(): array
    {
        if (! $this->cached) {
            $this->scan();
        }

        return $this->plugins;
    }

    /**
     * Get all enabled plugin modules.
     *
     * @return array<string, PluginModuleInterface> Enabled plugin modules
     */
    public function allEnabled(): array
    {
        return array_filter($this->all(), function (PluginModuleInterface $plugin): bool {
            return $plugin->isEnabled();
        });
    }

    /**
     * Get all disabled plugin modules.
     *
     * @return array<string, PluginModuleInterface> Disabled plugin modules
     */
    public function allDisabled(): array
    {
        return array_filter($this->all(), function (PluginModuleInterface $plugin): bool {
            return $plugin->isDisabled();
        });
    }

    /**
     * Get count of all plugin modules.
     *
     * @return int Number of plugin modules
     */
    public function count(): int
    {
        return count($this->all());
    }

    /**
     * Convert plugins to collection.
     *
     * @return CollectionInterface Plugin collection
     */
    public function toCollection(): CollectionInterface
    {
        $collection = new PluginCollection($this->all());

        return new LaravelCollectionAdapter($collection);
    }

    /**
     * Get plugins as Laravel collection.
     *
     * @return Collection Plugin collection
     */
    public function collect(): Collection
    {
        return new PluginCollection($this->all());
    }

    /**
     * Find a plugin module by name.
     *
     * @param  string  $name  Plugin name
     * @return PluginModuleInterface|null Plugin module or null if not found
     */
    public function find(string $name): ?PluginModuleInterface
    {
        $plugins = $this->all();

        return $plugins[strtolower($name)] ?? null;
    }

    /**
     * Find a plugin module by name or throw exception.
     *
     * @param  string  $name  Plugin name
     * @return PluginModuleInterface Plugin module
     *
     * @throws \Exception When plugin is not found
     */
    public function findOrFail(string $name): PluginModuleInterface
    {
        $plugin = $this->find($name);

        if (! $plugin instanceof PluginModuleInterface) {
            throw new \Exception("Plugin '{$name}' not found");
        }

        return $plugin;
    }

    /**
     * Check if a plugin module exists.
     *
     * @param  string  $name  Plugin name
     * @return bool True if plugin exists
     */
    public function has(string $name): bool
    {
        return $this->find($name) !== null;
    }

    /**
     * Scan for plugin modules in the plugins directory.
     *
     * @return array<string, PluginModuleInterface> Discovered plugin modules
     */
    public function scan(): array
    {
        $this->plugins = [];

        if (! is_dir($this->pluginsPath)) {
            $this->cached = true;

            return $this->plugins;
        }

        $pluginDirectories = $this->getPluginDirectories();

        foreach ($pluginDirectories as $pluginName => $pluginPath) {
            try {
                $plugin = $this->createPluginModule($pluginName, $pluginPath);
                if ($plugin instanceof PluginModuleInterface) {
                    $this->plugins[strtolower($pluginName)] = $plugin;
                }
            } catch (\Exception $e) {
                // Log error but continue scanning other plugins
                $this->logError("Failed to create plugin module for '{$pluginName}': {$e->getMessage()}");
            }
        }

        $this->cached = true;

        return $this->plugins;
    }

    /**
     * Register all plugin modules.
     */
    public function register(): void
    {
        foreach ($this->all() as $plugin) {
            try {
                if (method_exists($plugin, 'register')) {
                    $plugin->register();
                }
            } catch (\Exception $e) {
                $this->logError("Failed to register plugin '{$plugin->getName()}': {$e->getMessage()}");
            }
        }
    }

    /**
     * Boot all plugin modules.
     */
    public function boot(): void
    {
        foreach ($this->allEnabled() as $plugin) {
            try {
                if (method_exists($plugin, 'boot')) {
                    $plugin->boot();
                }
            } catch (\Exception $e) {
                $this->logError("Failed to boot plugin '{$plugin->getName()}': {$e->getMessage()}");
            }
        }
    }

    /**
     * Reset the plugin cache.
     */
    public function resetCache(): void
    {
        $this->plugins = [];
        $this->cached = false;
    }

    /**
     * Get plugin directories from the plugins path.
     *
     * @return array<string, string> Plugin name => plugin path pairs
     */
    protected function getPluginDirectories(): array
    {
        $directories = [];

        if (! is_dir($this->pluginsPath)) {
            return $directories;
        }

        $items = scandir($this->pluginsPath);
        if ($items === false) {
            return $directories;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $pluginPath = $this->pluginsPath.'/'.$item;

            if (! is_dir($pluginPath)) {
                continue;
            }

            // Check if directory contains a valid plugin main file
            $mainFile = $pluginPath.'/'.$item.'.php';
            if (file_exists($mainFile) && $this->parser->hasValidPluginHeaders($mainFile)) {
                $directories[$item] = $pluginPath;
            }
        }

        return $directories;
    }

    /**
     * Create a plugin module instance.
     *
     * @param  string  $pluginName  Plugin name
     * @param  string  $pluginPath  Plugin path
     * @return PluginModuleInterface|null Plugin module or null if creation failed
     */
    protected function createPluginModule(string $pluginName, string $pluginPath): ?PluginModuleInterface
    {
        try {
            // Parse plugin headers
            $mainFile = $pluginPath.'/'.$pluginName.'.php';
            $headers = $this->parser->parsePluginHeaders($mainFile);

            if (empty($headers)) {
                return null;
            }

            // Create plugin module
            $plugin = new LaravelPluginModule($pluginName, $pluginPath, $this->app);
            $plugin->setHeaders($headers);

            // Set enabled status based on WordPress active plugins
            $isActive = $this->isPluginActive($pluginName);
            $plugin->setEnabled($isActive);
            $plugin->setActive($isActive);

            return $plugin;
        } catch (\Exception $e) {
            $this->logError("Failed to create plugin module '{$pluginName}': {$e->getMessage()}");

            return null;
        }
    }

    /**
     * Check if a plugin is active in WordPress.
     *
     * @param  string  $pluginName  Plugin name
     * @return bool True if plugin is active
     */
    protected function isPluginActive(string $pluginName): bool
    {
        if (! function_exists('is_plugin_active')) {
            return false;
        }

        $pluginBasename = $pluginName.'/'.$pluginName.'.php';

        return is_plugin_active($pluginBasename);
    }

    /**
     * Get the plugins base path.
     *
     * @return string Plugins base path
     */
    protected function getPluginsBasePath(): string
    {
        // Try to get from WordPress constant first
        if (defined('WP_PLUGIN_DIR')) {
            return WP_PLUGIN_DIR;
        }

        // Fall back to configuration
        return rtrim((string) $this->app['config']->get('plugin.path', base_path('plugins')), '/');
    }

    /**
     * Add a plugin module to the repository.
     *
     * @param  string  $name  Plugin name
     * @param  PluginModuleInterface  $plugin  Plugin module
     */
    public function add(string $name, PluginModuleInterface $plugin): void
    {
        $this->plugins[strtolower($name)] = $plugin;
    }

    /**
     * Remove a plugin module from the repository.
     *
     * @param  string  $name  Plugin name
     * @return bool True if plugin was removed, false if not found
     */
    public function remove(string $name): bool
    {
        $key = strtolower($name);

        if (isset($this->plugins[$key])) {
            unset($this->plugins[$key]);

            return true;
        }

        return false;
    }

    /**
     * Get plugins by status.
     *
     * @param  bool  $status  Plugin enabled status
     * @return array<string, PluginModuleInterface> Filtered plugin modules
     */
    public function getByStatus(bool $status): array
    {
        return array_filter($this->all(), function (PluginModuleInterface $plugin) use ($status): bool {
            return $plugin->isEnabled() === $status;
        });
    }

    /**
     * Get plugins by custom status.
     *
     * @param  string  $status  Plugin status (active, inactive, enabled, disabled)
     * @return array<string, PluginModuleInterface> Filtered plugin modules
     */
    public function getByCustomStatus(string $status): array
    {
        return array_filter($this->all(), function (PluginModuleInterface $plugin) use ($status): bool {
            return match ($status) {
                'active' => $plugin->isActive(),
                'inactive' => ! $plugin->isActive(),
                'enabled' => $plugin->isEnabled(),
                'disabled' => $plugin->isDisabled(),
                default => true,
            };
        });
    }

    /**
     * Get ordered plugins.
     *
     * @param  string  $direction  Sort direction (asc or desc)
     * @return array<string, PluginModuleInterface> Ordered plugin modules
     */
    public function getOrdered(string $direction = 'asc'): array
    {
        $plugins = $this->all();

        if ($direction === 'desc') {
            return array_reverse($plugins, true);
        }

        return $plugins;
    }

    /**
     * Get plugins by author.
     *
     * @param  string  $author  Author name
     * @return array<string, PluginModuleInterface> Plugins by author
     */
    public function getByAuthor(string $author): array
    {
        return array_filter($this->all(), function (PluginModuleInterface $plugin) use ($author): bool {
            return $plugin->getAuthor() === $author;
        });
    }

    /**
     * Get plugins by text domain.
     *
     * @param  string  $textDomain  Text domain
     * @return array<string, PluginModuleInterface> Plugins with matching text domain
     */
    public function getByTextDomain(string $textDomain): array
    {
        return array_filter($this->all(), function (PluginModuleInterface $plugin) use ($textDomain): bool {
            return $plugin->getTextDomain() === $textDomain;
        });
    }

    /**
     * Search plugins by name or description.
     *
     * @param  string  $query  Search query
     * @return array<string, PluginModuleInterface> Matching plugins
     */
    public function search(string $query): array
    {
        $query = strtolower($query);

        return array_filter($this->all(), function (PluginModuleInterface $plugin) use ($query): bool {
            return str_contains(strtolower($plugin->getName()), $query) ||
                   str_contains(strtolower($plugin->getDescription()), $query);
        });
    }

    /**
     * Log error message.
     *
     * @param  string  $message  Error message
     */
    protected function logError(string $message): void
    {
        if (function_exists('error_log')) {
            error_log('[PluginRepository] '.$message);
        }
    }
}
