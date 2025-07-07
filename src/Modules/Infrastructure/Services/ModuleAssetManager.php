<?php

declare(strict_types=1);

namespace Pollora\Modules\Infrastructure\Services;

use Illuminate\Container\Container;
use Pollora\Asset\Application\Services\AssetManager;

/**
 * Generic module asset manager.
 *
 * This service can manage assets for any module type (themes, plugins, etc.)
 * providing a unified way to register and handle module-specific assets.
 */
class ModuleAssetManager
{
    public function __construct(
        protected Container $app
    ) {}

    /**
     * Setup asset management for a specific module.
     *
     * @param  string  $moduleName  Name of the module (for file paths)
     * @param  string  $modulePath  Path to the module
     * @param  string  $moduleType  Type of module (theme, plugin, etc.)
     * @param  string|null  $moduleSlug  Optional slug for the module (used for plugins)
     */
    public function setupModuleAssets(string $moduleName, string $modulePath, string $moduleType, ?string $moduleSlug = null): void
    {
        if (! $this->app->bound(AssetManager::class)) {
            return;
        }

        try {
            /** @var AssetManager $assetManager */
            $assetManager = $this->app->make(AssetManager::class);

            // Determine container name based on module type
            $containerName = $this->getContainerName($moduleType, $moduleSlug);

            $assetConfig = $this->getAssetConfiguration($moduleName, $modulePath, $moduleType);

            $assetManager->addContainer($containerName, $assetConfig);
        } catch (\Throwable $e) {
            if (function_exists('error_log')) {
                error_log("Failed to setup assets for module {$moduleName} ({$moduleType}): ".$e->getMessage());
            }
        }
    }

    /**
     * Get the container name based on module type and slug.
     */
    protected function getContainerName(string $moduleType, ?string $moduleSlug = null): string
    {
        return match ($moduleType) {
            'theme' => 'theme',
            'plugin' => 'plugin.'.($moduleSlug ?? 'unknown'),
            default => $moduleType.'.'.($moduleSlug ?? 'unknown'),
        };
    }

    /**
     * Get asset configuration for a module.
     */
    protected function getAssetConfiguration(string $moduleName, string $modulePath, string $moduleType): array
    {
        return [
            'hot_file' => public_path("{$moduleName}.hot"),
            'build_directory' => "build/{$moduleName}",
            'manifest_path' => 'manifest.json',
            'base_path' => 'resources/assets/',
            'module_path' => $modulePath,
            'module_type' => $moduleType,
        ];
    }

    /**
     * Load module include files recursively.
     */
    public function loadModuleIncludes(string $modulePath): void
    {
        $includeDirectory = $modulePath.'/inc';

        if (is_dir($includeDirectory)) {
            $this->loadFilesRecursively($includeDirectory);
        }
    }

    /**
     * Load all PHP files from a directory recursively.
     */
    protected function loadFilesRecursively(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        foreach (glob($directory.'/*.php') as $file) {
            require_once $file;
        }

        foreach (glob($directory.'/*', GLOB_ONLYDIR) as $subDir) {
            $this->loadFilesRecursively($subDir);
        }
    }

    /**
     * Register module specific Blade directives from a directives file.
     */
    public function registerModuleBladeDirectives(string $modulePath): void
    {
        $directivesPath = $modulePath.'/resources/directives.php';

        if (! file_exists($directivesPath)) {
            return;
        }

        try {
            $directives = require $directivesPath;

            if (is_array($directives) && class_exists('Illuminate\\Support\\Facades\\Blade')) {
                foreach ($directives as $name => $directive) {
                    \Illuminate\Support\Facades\Blade::directive($name, $directive);
                }
            }
        } catch (\Throwable $e) {
            if (function_exists('error_log')) {
                error_log("Failed to register Blade directives for module {$modulePath}: ".$e->getMessage());
            }
        }
    }
}
