<?php

declare(strict_types=1);

namespace Pollora\Modules\Infrastructure\Services;

use Illuminate\Container\Container;
use Pollora\Config\Domain\Contracts\ConfigRepositoryInterface;

/**
 * Generic module configuration loader.
 *
 * This service can load configuration for any module type (themes, plugins, etc.)
 * from their config directory and make it available via the Laravel config system.
 */
class ModuleConfigurationLoader
{
    public function __construct(
        protected Container $app,
        protected ConfigRepositoryInterface $configRepository
    ) {}

    /**
     * Load all configuration files from a module's config directory.
     *
     * @param  string  $modulePath  The path to the module
     * @param  string  $moduleType  The type of module (theme, plugin, etc.)
     * @param  string  $moduleName  The name of the module
     */
    public function loadModuleConfiguration(string $modulePath, string $moduleType, string $moduleName): void
    {
        $configPath = $modulePath.'/config';

        if (! is_dir($configPath)) {
            return;
        }

        foreach (glob($configPath.'/*.php') as $configFile) {
            $configName = basename($configFile, '.php');
            $configKey = "{$moduleType}.{$configName}";

            try {
                $configData = require $configFile;

                if (is_array($configData)) {
                    $this->configRepository->set($configKey, $configData);
                }
            } catch (\Throwable $e) {
                if (function_exists('error_log')) {
                    error_log("Failed to load {$moduleType} config {$configFile}: ".$e->getMessage());
                }
            }
        }
    }

    /**
     * Get a configuration value for a specific module.
     */
    public function getModuleConfig(string $moduleType, string $key, mixed $default = null): mixed
    {
        return $this->configRepository->get("{$moduleType}.{$key}", $default);
    }

    /**
     * Set a configuration value for a specific module.
     */
    public function setModuleConfig(string $moduleType, string $key, mixed $value): void
    {
        $this->configRepository->set("{$moduleType}.{$key}", $value);
    }

    /**
     * Check if a module configuration exists.
     */
    public function hasModuleConfig(string $moduleType, string $key): bool
    {
        return $this->configRepository->has("{$moduleType}.{$key}");
    }
}
