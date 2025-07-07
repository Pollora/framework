<?php

declare(strict_types=1);

namespace Pollora\Modules\Infrastructure\Services;

use Illuminate\Container\Container;
use Illuminate\View\ViewFinderInterface;
use Pollora\Asset\Application\Services\AssetManager;
use Pollora\Foundation\Support\IncludesFiles;

/**
 * Generic module asset manager.
 *
 * This service can manage assets for any module type (themes, plugins, etc.)
 * providing a unified way to register and handle module-specific assets.
 */
class ModuleAssetManager
{
    use IncludesFiles;

    public function __construct(
        protected Container $app
    ) {}

    /**
     * Setup asset management for a specific module.
     *
     * @param  string  $moduleName  Name of the module (for file paths)
     * @param  string  $modulePath  Path to the module
     * @param  string  $moduleType  Type of module (theme, plugin, etc.)
     * @param  string|null  $moduleSlug  Optional slug for the module
     */
    public function setupModuleAssets(string $moduleName, string $modulePath, string $moduleType, ?string $moduleSlug = null): void
    {
        try {
            // Setup asset container
            if ($this->app->bound(AssetManager::class)) {
                /** @var AssetManager $assetManager */
                $assetManager = $this->app->make(AssetManager::class);

                // Determine container name based on module type
                $containerName = $this->getContainerName($moduleType, $moduleSlug ?? $moduleName);

                $assetConfig = $this->getAssetConfiguration($moduleName, $modulePath, $moduleType);

                $assetManager->addContainer($containerName, $assetConfig);
            }

            // Register view paths for the module
            $this->registerModuleViewPaths($modulePath, $moduleType, $moduleSlug);

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
            'build_directory' => "build/{$moduleType}/{$moduleName}",
            'manifest_path' => 'manifest.json',
            'base_path' => 'resources/assets/',
            'module_path' => $modulePath,
            'module_type' => $moduleType,
        ];
    }

    /**
     * Load module include files from app/inc directory.
     */
    public function loadModuleIncludes(string $modulePath): void
    {
        $includeDirectory = $modulePath.'/app/inc';

        if (is_dir($includeDirectory)) {
            $this->includes($includeDirectory);
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

            if (is_array($directives) && class_exists(\Illuminate\Support\Facades\Blade::class)) {
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

    /**
     * Register module view paths with Laravel's view finder.
     *
     * @param string $modulePath Path to the module
     * @param string $moduleType Type of module (theme, plugin, etc.)
     * @param string|null $moduleSlug Optional slug for the module
     * @return void
     */
    public function registerModuleViewPaths(string $modulePath, string $moduleType, ?string $moduleSlug = null): void
    {
        if (! $this->app->bound('view')) {
            return;
        }

        try {
            /** @var \Illuminate\View\Factory $viewFactory */
            $viewFactory = $this->app->make('view');
            $viewFinder = $viewFactory->getFinder();

            if (! $viewFinder instanceof ViewFinderInterface) {
                return;
            }

            // Register main view paths
            $viewPaths = $this->getModuleViewPaths($modulePath, $moduleType);
            
            foreach ($viewPaths as $viewPath) {
                if (is_dir($viewPath)) {
                    $viewFinder->addLocation($viewPath);
                }
            }

            // Register view namespace if module has a slug
            if ($moduleSlug && is_dir($modulePath.'/resources/views')) {
                $viewFactory->addNamespace($moduleSlug, $modulePath.'/resources/views');
            }

        } catch (\Throwable $e) {
            if (function_exists('error_log')) {
                error_log("Failed to register view paths for module {$modulePath} ({$moduleType}): ".$e->getMessage());
            }
        }
    }

    /**
     * Get all possible view paths for a module.
     *
     * @param string $modulePath Path to the module
     * @param string $moduleType Type of module (theme, plugin, etc.)
     * @return array Array of view paths
     */
    protected function getModuleViewPaths(string $modulePath, string $moduleType): array
    {
        $paths = [];

        // Standard Laravel view paths
        $standardPaths = [
            $modulePath.'/resources/views',
            $modulePath.'/views',
        ];

        // Theme-specific paths (for backward compatibility)
        if ($moduleType === 'theme') {
            $standardPaths[] = $modulePath; // Theme root for WordPress templates
        }

        foreach ($standardPaths as $path) {
            if (is_dir($path)) {
                $paths[] = $path;
            }
        }

        return $paths;
    }
}
