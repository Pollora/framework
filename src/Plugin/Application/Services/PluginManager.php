<?php

declare(strict_types=1);

namespace Pollora\Plugin\Application\Services;

use Illuminate\Contracts\Translation\Translator;
use Illuminate\Support\Str;
use Pollora\Application\Application\Services\ConsoleDetectionService;
use Pollora\Collection\Domain\Contracts\CollectionInterface;
use Pollora\Foundation\Support\IncludesFiles;
use Pollora\Modules\Domain\Contracts\ModuleRepositoryInterface;
use Pollora\Plugin\Domain\Contracts\PluginModuleInterface;
use Pollora\Plugin\Domain\Exceptions\PluginException;
use Pollora\Plugin\Domain\Models\PluginMetadata;
use Pollora\Plugin\Domain\Support\PluginCollection;
use Psr\Container\ContainerInterface;

/**
 * Plugin management service for WordPress plugins.
 *
 * Handles plugin discovery, activation, deactivation, and management operations.
 * Provides a centralized interface for working with WordPress plugins in the
 * Pollora framework context.
 */
class PluginManager
{
    use IncludesFiles;

    /**
     * Plugin discovery service.
     *
     * @var mixed
     */
    public $discovery;

    /**
     * Plugin configuration.
     *
     * @var array
     */
    protected array $config;

    /**
     * Current plugin instance.
     *
     * @var PluginMetadata|null
     */
    protected ?PluginMetadata $plugin = null;

    /**
     * Console detection service.
     *
     * @var ConsoleDetectionService
     */
    protected ConsoleDetectionService $consoleDetectionService;

    /**
     * Create a new PluginManager instance.
     *
     * @param ContainerInterface $app Application container
     * @param Translator|null $localeLoader Translation loader
     * @param ModuleRepositoryInterface|null $repository Module repository
     * @param ConsoleDetectionService|null $consoleDetectionService Console detection service
     */
    public function __construct(
        protected ContainerInterface $app,
        protected ?Translator $localeLoader,
        protected ?ModuleRepositoryInterface $repository = null,
        ?ConsoleDetectionService $consoleDetectionService = null
    ) {
        $this->consoleDetectionService = $consoleDetectionService ?? app(ConsoleDetectionService::class);
    }

    /**
     * Get the manager instance.
     *
     * @return static Manager instance
     */
    public function instance(): static
    {
        return $this;
    }

    /**
     * Create a PluginMetadata instance.
     *
     * This method exists primarily to make testing easier.
     *
     * @param string $pluginName Plugin name
     * @param string $pluginsPath Plugins base path
     * @return PluginMetadata Plugin metadata instance
     */
    protected function createPluginMetadata(string $pluginName, string $pluginsPath): PluginMetadata
    {
        return new PluginMetadata($pluginName, $pluginsPath);
    }

    /**
     * Load a plugin by name.
     *
     * @param string $pluginName Plugin name
     * @return void
     * @throws PluginException When plugin name is empty or directory not found
     */
    public function load(string $pluginName): void
    {
        if ($pluginName === '' || $pluginName === '0') {
            throw new PluginException('Plugin name cannot be empty.');
        }

        $plugin = $this->createPluginMetadata($pluginName, $this->getPluginsPath());
        $this->plugin = $plugin;

        if (! is_dir($plugin->getBasePath()) && ! $this->consoleDetectionService->isConsole()) {
            throw new PluginException("Plugin directory {$plugin->getName()} not found.");
        }

        $plugin->loadConfiguration();

        if ($this->localeLoader instanceof \Illuminate\Contracts\Translation\Translator) {
            $this->localeLoader->addNamespace($pluginName, $plugin->getLanguagePath());
        }
    }

    /**
     * Get all available plugins from the plugins directory.
     *
     * @return array Available plugin names
     */
    public function getAvailablePlugins(): array
    {
        $path = $this->getPluginsPath();

        if (! file_exists($path)) {
            return [];
        }

        return array_filter(scandir($path), function ($entry) use ($path): bool {
            if ($entry === '.' || $entry === '..') {
                return false;
            }

            $pluginInfo = new PluginMetadata($entry, $path);

            // Check if it's a valid plugin directory with a main plugin file
            return is_dir($pluginInfo->getBasePath()) && 
                   file_exists($pluginInfo->getMainFilePath());
        });
    }

    /**
     * Get the plugins directory path.
     *
     * @return string Plugins directory path
     */
    protected function getPluginsPath(): string
    {
        // Try WordPress constant first
        if (defined('WP_PLUGIN_DIR')) {
            return WP_PLUGIN_DIR;
        }

        // Fall back to configuration or WordPress default location
        return rtrim((string) $this->app['config']->get('plugin.path', public_path('content/plugins')), '/');
    }

    /**
     * Get the active plugin name.
     *
     * @return string|bool Active plugin name or false if not available
     */
    public function active(): string|bool
    {
        if (! function_exists('get_option')) {
            return false;
        }

        $activePlugins = get_option('active_plugins', []);
        
        return ! empty($activePlugins) ? $activePlugins[0] : false;
    }

    /**
     * Get plugin path with optional subpath.
     *
     * @param string $pluginName Plugin name
     * @param string $path Optional subpath
     * @return string Full plugin path
     */
    public function path(string $pluginName, string $path = ''): string
    {
        return $this->getPluginsPath().'/'.$pluginName.'/'.ltrim($path, '/');
    }

    /**
     * Get plugin application path.
     *
     * @param string $pluginName Plugin name
     * @param string $path Optional subpath
     * @return string Plugin application path
     */
    public function getPluginAppPath(string $pluginName, string $path = ''): string
    {
        $pluginNamespace = Str::studly($pluginName);
        $path = trim($path, '/');

        if ($path !== '') {
            $segments = explode('/', $path, 2);
            if (count($segments) > 1) {
                return app_path($segments[0].'/'.$pluginNamespace.'/'.$segments[1]);
            }
        }

        return app_path($path);
    }

    /**
     * Get current plugin metadata.
     *
     * @return PluginMetadata|null Current plugin metadata
     */
    public function plugin(): ?PluginMetadata
    {
        return $this->plugin;
    }

    /**
     * Get all available plugins as modules.
     *
     * @return CollectionInterface|null Plugin collection
     */
    public function getAllPlugins(): ?CollectionInterface
    {
        return $this->repository?->toCollection();
    }

    /**
     * Get all available plugins as array.
     *
     * @return array Plugin modules array
     */
    public function getAllPluginsAsArray(): array
    {
        return $this->repository?->all() ?? [];
    }

    /**
     * Get enabled plugins.
     *
     * @return array Enabled plugin modules
     */
    public function getEnabledPlugins(): array
    {
        return $this->repository?->allEnabled() ?? [];
    }

    /**
     * Get disabled plugins.
     *
     * @return array Disabled plugin modules
     */
    public function getDisabledPlugins(): array
    {
        return $this->repository?->allDisabled() ?? [];
    }

    /**
     * Get active plugins.
     *
     * @return array Active plugin modules
     */
    public function getActivePlugins(): array
    {
        $allPlugins = $this->getAllPluginsAsArray();
        
        return array_filter($allPlugins, function (PluginModuleInterface $plugin): bool {
            return $plugin->isActive();
        });
    }

    /**
     * Get inactive plugins.
     *
     * @return array Inactive plugin modules
     */
    public function getInactivePlugins(): array
    {
        $allPlugins = $this->getAllPluginsAsArray();
        
        return array_filter($allPlugins, function (PluginModuleInterface $plugin): bool {
            return ! $plugin->isActive();
        });
    }

    /**
     * Find plugin module by name.
     *
     * @param string $name Plugin name
     * @return PluginModuleInterface|null Plugin module or null if not found
     */
    public function findPlugin(string $name): ?PluginModuleInterface
    {
        if (! $this->repository instanceof \Pollora\Modules\Domain\Contracts\ModuleRepositoryInterface) {
            return null;
        }

        $plugin = $this->repository->find($name);

        return $plugin instanceof PluginModuleInterface ? $plugin : null;
    }

    /**
     * Check if plugin exists.
     *
     * @param string $name Plugin name
     * @return bool True if plugin exists
     */
    public function hasPlugin(string $name): bool
    {
        return $this->repository?->has($name) ?? false;
    }

    /**
     * Check if plugin is active.
     *
     * @param string $name Plugin name
     * @return bool True if plugin is active
     */
    public function isPluginActive(string $name): bool
    {
        $plugin = $this->findPlugin($name);
        
        return $plugin?->isActive() ?? false;
    }

    /**
     * Activate a plugin.
     *
     * @param string $name Plugin name
     * @return void
     * @throws PluginException When plugin cannot be activated
     */
    public function activatePlugin(string $name): void
    {
        $plugin = $this->findPlugin($name);
        
        if (! $plugin instanceof PluginModuleInterface) {
            throw PluginException::pluginNotFound($name);
        }

        try {
            $plugin->activate();
            
            // Call WordPress activation hook if available
            if (function_exists('do_action')) {
                do_action('activate_plugin', $plugin->getBasename());
            }
        } catch (\Exception $e) {
            throw PluginException::activationFailed($name, $e->getMessage(), 0, $e);
        }
    }

    /**
     * Deactivate a plugin.
     *
     * @param string $name Plugin name
     * @return void
     * @throws PluginException When plugin cannot be deactivated
     */
    public function deactivatePlugin(string $name): void
    {
        $plugin = $this->findPlugin($name);
        
        if (! $plugin instanceof PluginModuleInterface) {
            throw PluginException::pluginNotFound($name);
        }

        try {
            $plugin->deactivate();
            
            // Call WordPress deactivation hook if available
            if (function_exists('do_action')) {
                do_action('deactivate_plugin', $plugin->getBasename());
            }
        } catch (\Exception $e) {
            throw PluginException::deactivationFailed($name, $e->getMessage(), 0, $e);
        }
    }

    /**
     * Get plugin information.
     *
     * @param string $name Plugin name
     * @return array Plugin information
     * @throws PluginException When plugin is not found
     */
    public function getPluginInfo(string $name): array
    {
        $plugin = $this->findPlugin($name);

        if (! $plugin instanceof PluginModuleInterface) {
            throw PluginException::pluginNotFound($name);
        }

        return [
            'name' => $plugin->getName(),
            'description' => $plugin->getDescription(),
            'version' => $plugin->getVersion(),
            'author' => $plugin->getAuthor(),
            'path' => $plugin->getPath(),
            'enabled' => $plugin->isEnabled(),
            'active' => $plugin->isActive(),
            'slug' => $plugin->getSlug(),
            'basename' => $plugin->getBasename(),
            'main_file' => $plugin->getMainFile(),
            'text_domain' => $plugin->getTextDomain(),
            'domain_path' => $plugin->getDomainPath(),
            'network_wide' => $plugin->isNetworkWide(),
            'plugin_uri' => $plugin->getPluginUri(),
            'author_uri' => $plugin->getAuthorUri(),
            'requires_wp' => $plugin->getRequiredWordPressVersion(),
            'tested_up_to' => $plugin->getTestedWordPressVersion(),
            'requires_php' => $plugin->getRequiredPhpVersion(),
        ];
    }

    /**
     * Scan for new plugins.
     *
     * @return array Discovered plugins
     */
    public function scanPlugins(): array
    {
        return $this->repository?->scan() ?? [];
    }

    /**
     * Register all plugins.
     *
     * @return void
     */
    public function registerPlugins(): void
    {
        $this->repository?->register();
    }

    /**
     * Get plugin count.
     *
     * @return int Number of plugins
     */
    public function getPluginCount(): int
    {
        return $this->repository?->count() ?? 0;
    }

    /**
     * Validate plugin structure.
     *
     * @param string $name Plugin name
     * @return array Validation result
     */
    public function validatePlugin(string $name): array
    {
        $plugin = $this->findPlugin($name);

        if (! $plugin instanceof PluginModuleInterface) {
            return [
                'valid' => false,
                'errors' => ['Plugin not found'],
            ];
        }

        $errors = [];
        $path = $plugin->getPath();

        // Check if directory exists
        if (! is_dir($path)) {
            $errors[] = 'Plugin directory does not exist';
        }

        // Check if main plugin file exists
        if (! file_exists($plugin->getMainFile())) {
            $errors[] = 'Main plugin file does not exist';
        }

        // Check for valid plugin headers
        if (empty($plugin->getHeaders())) {
            $errors[] = 'Plugin headers are missing or invalid';
        }

        return [
            'valid' => $errors === [],
            'errors' => $errors,
        ];
    }

    /**
     * Reset plugin cache.
     *
     * @return void
     */
    public function resetCache(): void
    {
        $this->repository?->resetCache();
        if (method_exists($this->discovery, 'resetCache')) {
            $this->discovery->resetCache();
        }
    }

    /**
     * Get plugins as a specialized collection.
     *
     * @return PluginCollection Plugin collection
     */
    public function collect(): PluginCollection
    {
        return new PluginCollection($this->getAllPluginsAsArray());
    }

    /**
     * Enable a plugin.
     *
     * @param string $name Plugin name
     * @return void
     * @throws PluginException When plugin cannot be enabled
     */
    public function enablePlugin(string $name): void
    {
        $plugin = $this->findPlugin($name);
        
        if (! $plugin instanceof PluginModuleInterface) {
            throw PluginException::pluginNotFound($name);
        }

        $plugin->enable();
    }

    /**
     * Disable a plugin.
     *
     * @param string $name Plugin name
     * @return void
     * @throws PluginException When plugin cannot be disabled
     */
    public function disablePlugin(string $name): void
    {
        $plugin = $this->findPlugin($name);
        
        if (! $plugin instanceof PluginModuleInterface) {
            throw PluginException::pluginNotFound($name);
        }

        $plugin->disable();
    }

    /**
     * Get network-wide plugins.
     *
     * @return array Network-wide plugin modules
     */
    public function getNetworkWidePlugins(): array
    {
        $allPlugins = $this->getAllPluginsAsArray();
        
        return array_filter($allPlugins, function (PluginModuleInterface $plugin): bool {
            return $plugin->isNetworkWide();
        });
    }

    /**
     * Get single-site plugins.
     *
     * @return array Single-site plugin modules
     */
    public function getSingleSitePlugins(): array
    {
        $allPlugins = $this->getAllPluginsAsArray();
        
        return array_filter($allPlugins, function (PluginModuleInterface $plugin): bool {
            return ! $plugin->isNetworkWide();
        });
    }
}