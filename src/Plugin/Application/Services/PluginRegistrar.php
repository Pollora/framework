<?php

declare(strict_types=1);

namespace Pollora\Plugin\Application\Services;

use Pollora\Modules\Domain\Contracts\ModuleDiscoveryOrchestratorInterface;
use Pollora\Modules\Domain\Contracts\ModuleRepositoryInterface;
use Pollora\Modules\Infrastructure\Services\ModuleAssetManager;
use Pollora\Modules\Infrastructure\Services\ModuleComponentManager;
use Pollora\Modules\Infrastructure\Services\ModuleConfigurationLoader;
use Pollora\Plugin\Domain\Contracts\PluginModuleInterface;
use Pollora\Plugin\Domain\Models\LaravelPluginModule;
use Pollora\Plugin\Infrastructure\Repositories\PluginRepository;
use Pollora\Plugin\Infrastructure\Services\WordPressPluginParser;
use Psr\Container\ContainerInterface;

/**
 * Plugin registration service.
 *
 * Handles the registration and initialization of WordPress plugins within
 * the Pollora framework. Manages plugin discovery, service provider registration,
 * and integration with the Laravel application container.
 */
class PluginRegistrar
{
    /**
     * Collection of registered plugins.
     *
     * @var array<string, PluginModuleInterface>
     */
    private array $registeredPlugins = [];

    /**
     * Create a new PluginRegistrar instance.
     *
     * @param ContainerInterface $app Application container
     * @param WordPressPluginParser $pluginParser Plugin parser service
     */
    public function __construct(
        protected ContainerInterface $app,
        protected WordPressPluginParser $pluginParser
    ) {}

    /**
     * Register a plugin by name and path.
     *
     * @param string $pluginName Plugin name
     * @param string $pluginPath Plugin path
     * @return PluginModuleInterface Registered plugin module
     */
    public function register(string $pluginName, string $pluginPath): PluginModuleInterface
    {
        // Parse plugin headers from main plugin file
        $mainFilePath = rtrim($pluginPath, '/').'/'.$pluginName.'.php';
        $pluginData = $this->pluginParser->parsePluginHeaders($mainFilePath);

        // Create the plugin module
        $plugin = $this->createPluginModule($pluginName, $pluginPath);
        $plugin->registerAutoloading();
        $plugin->setHeaders($pluginData);
        $plugin->setEnabled(true);

        // Store as registered plugin
        $this->registeredPlugins[$pluginName] = $plugin;

        // Invalidate repository cache and discover structures
        $this->invalidateRepositoryCache();
        $this->discoverPluginStructures($plugin);

        // Load plugin configuration
        $this->loadPluginConfiguration($plugin);

        // Setup plugin components
        $this->setupPluginComponents($plugin);

        // Setup plugin assets and includes
        $this->setupPluginAssets($plugin);

        // Register and boot the plugin
        $plugin->register();
        $plugin->boot();

        return $plugin;
    }

    /**
     * Register multiple plugins from an array.
     *
     * @param array $plugins Array of plugin names and paths
     * @return array<string, PluginModuleInterface> Registered plugin modules
     */
    public function registerMultiple(array $plugins): array
    {
        $registeredPlugins = [];

        foreach ($plugins as $pluginName => $pluginPath) {
            $registeredPlugins[$pluginName] = $this->register($pluginName, $pluginPath);
        }

        return $registeredPlugins;
    }

    /**
     * Register plugins from WordPress active plugins list.
     *
     * @return array<string, PluginModuleInterface> Registered plugin modules
     */
    public function registerActivePlugins(): array
    {
        if (! function_exists('get_option')) {
            return [];
        }

        $activePlugins = get_option('active_plugins', []);
        $registeredPlugins = [];

        foreach ($activePlugins as $pluginBasename) {
            $pluginName = dirname($pluginBasename);
            $pluginPath = WP_PLUGIN_DIR.'/'.$pluginName;

            if (is_dir($pluginPath) && $pluginName !== '.') {
                $registeredPlugins[$pluginName] = $this->register($pluginName, $pluginPath);
            }
        }

        return $registeredPlugins;
    }

    /**
     * Get a registered plugin by name.
     *
     * @param string $pluginName Plugin name
     * @return PluginModuleInterface|null Plugin module or null if not found
     */
    public function getRegisteredPlugin(string $pluginName): ?PluginModuleInterface
    {
        return $this->registeredPlugins[$pluginName] ?? null;
    }

    /**
     * Get all registered plugins.
     *
     * @return array<string, PluginModuleInterface> All registered plugin modules
     */
    public function getRegisteredPlugins(): array
    {
        return $this->registeredPlugins;
    }

    /**
     * Check if a plugin is registered.
     *
     * @param string $pluginName Plugin name
     * @return bool True if plugin is registered
     */
    public function isPluginRegistered(string $pluginName): bool
    {
        return isset($this->registeredPlugins[$pluginName]);
    }

    /**
     * Unregister a plugin.
     *
     * @param string $pluginName Plugin name
     * @return bool True if plugin was unregistered, false if not found
     */
    public function unregister(string $pluginName): bool
    {
        if (! isset($this->registeredPlugins[$pluginName])) {
            return false;
        }

        $plugin = $this->registeredPlugins[$pluginName];
        $plugin->disable();

        unset($this->registeredPlugins[$pluginName]);

        return true;
    }

    /**
     * Reset all registered plugins.
     *
     * @return void
     */
    public function resetRegisteredPlugins(): void
    {
        foreach ($this->registeredPlugins as $plugin) {
            $plugin->disable();
        }

        $this->registeredPlugins = [];
        $this->invalidateRepositoryCache();
    }

    /**
     * Create plugin module instance.
     *
     * @param string $pluginName Plugin name
     * @param string $pluginPath Plugin path
     * @return LaravelPluginModule Plugin module instance
     */
    protected function createPluginModule(string $pluginName, string $pluginPath): LaravelPluginModule
    {
        if ($this->app->has('app') && method_exists($this->app->get('app'), 'make')) {
            return new LaravelPluginModule($pluginName, $pluginPath, $this->app->get('app'));
        }

        return new LaravelPluginModule($pluginName, $pluginPath, $this->app);
    }

    /**
     * Invalidate the plugin repository cache.
     *
     * @return void
     */
    protected function invalidateRepositoryCache(): void
    {
        if (! $this->app->has(ModuleRepositoryInterface::class)) {
            return;
        }

        try {
            $repository = $this->app->get(ModuleRepositoryInterface::class);

            if ($repository instanceof PluginRepository) {
                $repository->resetCache();
            }
        } catch (\Exception $e) {
            $this->logError('Failed to invalidate plugin repository cache: '.$e->getMessage());
        }
    }

    /**
     * Perform on-demand discovery for plugin structures.
     *
     * @param PluginModuleInterface $plugin Plugin module
     * @return void
     */
    protected function discoverPluginStructures(PluginModuleInterface $plugin): void
    {
        if (! $this->app->has(ModuleDiscoveryOrchestratorInterface::class)) {
            return;
        }

        try {
            $discoveryService = $this->app->get(ModuleDiscoveryOrchestratorInterface::class);

            $discoveryService->discover($plugin->getPath());
        } catch (\Exception $e) {
            $this->logError("Plugin discovery error for {$plugin->getName()}: ".$e->getMessage());
        }
    }

    /**
     * Load plugin-specific configuration.
     *
     * @param PluginModuleInterface $plugin Plugin module
     * @return void
     */
    protected function loadPluginConfiguration(PluginModuleInterface $plugin): void
    {
        if (! $this->app->has(ModuleConfigurationLoader::class)) {
            return;
        }

        try {
            /** @var ModuleConfigurationLoader $configLoader */
            $configLoader = $this->app->get(ModuleConfigurationLoader::class);

            $configLoader->loadModuleConfiguration(
                $plugin->getPath(),
                'plugin'
            );
        } catch (\Exception $e) {
            $this->logError('Failed to load plugin configuration: '.$e->getMessage());
        }
    }

    /**
     * Setup plugin-specific components.
     *
     * @param PluginModuleInterface $plugin Plugin module
     * @return void
     */
    protected function setupPluginComponents(PluginModuleInterface $plugin): void
    {
        if (! $this->app->has(ModuleComponentManager::class)) {
            return;
        }

        try {
            /** @var ModuleComponentManager $componentManager */
            $componentManager = $this->app->get(ModuleComponentManager::class);

            // Only register components that can be automatically instantiated
            // Domain entities like PostType, Taxonomy, etc. should not be registered
            // as they require specific constructor parameters
            $pluginComponents = [
                // Add service classes here that can be auto-instantiated if needed
                // Example: \Plugin\MyPlugin\Services\ExampleService::class,
            ];

            $moduleId = 'plugin.'.$plugin->getLowerName();

            if (!empty($pluginComponents)) {
                $componentManager->registerModuleComponents($moduleId, $pluginComponents);
                $componentManager->initializeModuleComponents($moduleId);
            }
        } catch (\Exception $e) {
            $this->logError('Failed to setup plugin components: '.$e->getMessage());
        }
    }

    /**
     * Setup plugin assets and includes.
     *
     * @param PluginModuleInterface $plugin Plugin module
     * @return void
     */
    protected function setupPluginAssets(PluginModuleInterface $plugin): void
    {
        if (! $this->app->has(ModuleAssetManager::class)) {
            return;
        }

        try {
            /** @var ModuleAssetManager $assetManager */
            $assetManager = $this->app->get(ModuleAssetManager::class);

            // Setup asset management (plugin uses 'plugin' as container name)
            $assetManager->setupModuleAssets(
                $plugin->getLowerName(),
                $plugin->getPath(),
                'plugin'
            );

            // Load plugin includes
            $assetManager->loadModuleIncludes($plugin->getPath());

            // Register Blade directives if plugin has views
            if (method_exists($plugin, 'hasViews') && $plugin->hasViews()) {
                $assetManager->registerModuleBladeDirectives($plugin->getPath());
            }
        } catch (\Exception $e) {
            $this->logError('Failed to setup plugin assets: '.$e->getMessage());
        }
    }

    /**
     * Get count of registered plugins.
     *
     * @return int Number of registered plugins
     */
    public function getRegisteredPluginCount(): int
    {
        return count($this->registeredPlugins);
    }

    /**
     * Get registered plugins by status.
     *
     * @param string $status Plugin status (active, inactive, enabled, disabled)
     * @return array<string, PluginModuleInterface> Filtered plugin modules
     */
    public function getRegisteredPluginsByStatus(string $status): array
    {
        return array_filter($this->registeredPlugins, function (PluginModuleInterface $plugin) use ($status): bool {
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
     * Check if debug mode is enabled.
     *
     * @return bool True if debug mode is enabled
     */
    protected function isDebugMode(): bool
    {
        return defined('WP_DEBUG') && WP_DEBUG;
    }

    /**
     * Log error message.
     *
     * @param string $message Error message
     * @return void
     */
    protected function logError(string $message): void
    {
        if (function_exists('error_log')) {
            error_log($message);
        }
    }
}
