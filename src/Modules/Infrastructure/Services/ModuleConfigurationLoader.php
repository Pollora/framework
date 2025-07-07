<?php

declare(strict_types=1);

namespace Pollora\Modules\Infrastructure\Services;

use Illuminate\Contracts\Container\Container;
use Pollora\Config\Domain\Contracts\ConfigRepositoryInterface;
use Pollora\Hook\Domain\Contracts\Action;

/**
 * Generic module configuration loader.
 *
 * This service can load configuration for any module type (themes, plugins, etc.)
 * from their config directory and make it available via the Laravel config system.
 */
class ModuleConfigurationLoader
{
    protected \Pollora\Hook\Domain\Contracts\Action $actionService;

    public function __construct(
        protected Container $app,
        protected ConfigRepositoryInterface $configRepository
    ) {
        $this->action = $this->app->get(Action::class);
    }

    /**
     * Load all configuration files from a module's config directory.
     *
     * @param  string  $modulePath  The path to the module
     * @param  string  $moduleType  The type of module (theme, plugin, etc.)
     */
    public function loadModuleConfiguration(string $modulePath, string $moduleType): void
    {
        $this->action->add('after_setup_theme', function () use ($modulePath, $moduleType) {
            $this->loadConfigurationFiles($modulePath, $moduleType);
        });
    }

    /**
     * Get a configuration value for a specific module.
     */
    public function getModuleConfig(string $moduleType, string $key, mixed $default = null): mixed
    {
        return $this->configRepository->get($this->buildConfigKey($moduleType, $key), $default);
    }

    /**
     * Set a configuration value for a specific module.
     */
    public function setModuleConfig(string $moduleType, string $key, mixed $value): void
    {
        $this->configRepository->set($this->buildConfigKey($moduleType, $key), $value);
    }

    /**
     * Check if a module configuration exists.
     */
    public function hasModuleConfig(string $moduleType, string $key): bool
    {
        return $this->configRepository->has($this->buildConfigKey($moduleType, $key));
    }

    /**
     * Load all configuration files from the module's config directory.
     */
    private function loadConfigurationFiles(string $modulePath, string $moduleType): void
    {
        $configPath = $modulePath . '/config';

        if (!is_dir($configPath)) {
            return;
        }

        $configFiles = glob($configPath . '/*.php') ?: [];

        foreach ($configFiles as $configFile) {
            $this->loadSingleConfigFile($configFile, $moduleType);
        }
    }

    /**
     * Load a single configuration file.
     */
    private function loadSingleConfigFile(string $configFile, string $moduleType): void
    {
        $configName = basename($configFile, '.php');
        $configKey = $this->buildConfigKey($moduleType, $configName);

        try {
            $configData = require $configFile;

            if (is_array($configData)) {
                $this->configRepository->set($configKey, $configData);
            }
        } catch (\Throwable $e) {
            error_log("Failed to load {$moduleType} config {$configFile}: ".$e->getMessage());
        }
    }

    /**
     * Build the configuration key for a module.
     */
    private function buildConfigKey(string $moduleType, string $key): string
    {
        return "{$moduleType}.{$key}";
    }
}
