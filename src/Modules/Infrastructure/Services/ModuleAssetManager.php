<?php

declare(strict_types=1);

namespace Pollora\Modules\Infrastructure\Services;

use Illuminate\Container\Container;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Factory;
use Illuminate\View\FileViewFinder;
use Illuminate\View\ViewFinderInterface;
use Pollora\Asset\Application\Services\AssetManager;
use Pollora\Foundation\Support\IncludesFiles;
use Psr\Log\LoggerInterface;

/**
 * Generic module asset manager.
 *
 * This service can manage assets for any module type (themes, plugins, etc.)
 * providing a unified way to register and handle module-specific assets.
 */
class ModuleAssetManager
{
    use IncludesFiles;

    private ?LoggerInterface $logger = null;

    /**
     * Root directory of every module whose assets were set up, keyed by asset container name.
     *
     * @var array<string, string>
     */
    private array $moduleRoots = [];

    public function __construct(
        protected Container $app
    ) {
        try {
            $this->logger = $app->make(LoggerInterface::class);
        } catch (\Throwable) {
        }
    }

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
            $this->setupModuleAssetContainer($moduleName, $modulePath, $moduleType, $moduleSlug);

            // Register view paths for the module
            $this->registerModuleViewPaths($modulePath, $moduleType, $moduleSlug);

        } catch (\Throwable $throwable) {
            $this->logger?->error(sprintf('Failed to setup assets for module %s (%s)', $moduleName, $moduleType), ['exception' => $throwable]);
        }
    }

    /**
     * Add the asset container of a module, and nothing else — no view paths.
     *
     * @return string|null The container name, or null when there is no asset manager
     */
    public function setupModuleAssetContainer(string $moduleName, string $modulePath, string $moduleType, ?string $moduleSlug = null): ?string
    {
        if (! $this->app->bound(AssetManager::class)) {
            return null;
        }

        /** @var AssetManager $assetManager */
        $assetManager = $this->app->make(AssetManager::class);
        $containerName = $this->getContainerName($moduleType, $moduleSlug ?? $moduleName);

        $assetManager->addContainer($containerName, $this->getAssetConfiguration($moduleName, $modulePath, $moduleType));
        $this->moduleRoots[$containerName] = $modulePath;

        return $containerName;
    }

    /**
     * Root directory of every module whose assets were set up, keyed by asset container name.
     *
     * @return array<string, string>
     */
    public function getModuleRoots(): array
    {
        return $this->moduleRoots;
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
            'hot_file' => public_path($moduleName.'.hot'),
            'build_directory' => sprintf('build/%s/%s', $moduleType, $moduleName),
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

            if (is_array($directives) && class_exists(Blade::class)) {
                foreach ($directives as $name => $directive) {
                    Blade::directive($name, $directive);
                }
            }
        } catch (\Throwable $throwable) {
            $this->logger?->error(sprintf('Failed to register Blade directives for module %s', $modulePath), ['exception' => $throwable]);
        }
    }

    /**
     * Register module view paths with Laravel's view finder.
     *
     * Registers view paths for modules with priority handling to ensure module
     * views (including error views) take precedence over framework defaults.
     * Error views from modules will be discovered before Laravel's built-in
     * error views, allowing modules to provide custom error pages.
     *
     * @param  string  $modulePath  Path to the module
     * @param  string  $moduleType  Type of module (theme, plugin, etc.)
     * @param  string|null  $moduleSlug  Optional slug for the module
     */
    public function registerModuleViewPaths(string $modulePath, string $moduleType, ?string $moduleSlug = null): void
    {
        if (! $this->app->bound('view')) {
            return;
        }

        try {
            /** @var Factory $viewFactory */
            $viewFactory = $this->app->make('view');
            $viewFinder = $viewFactory->getFinder();

            if (! $viewFinder instanceof ViewFinderInterface) {
                return;
            }

            // Register main view paths with priority for module views
            $viewPaths = $this->getModuleViewPaths($modulePath, $moduleType);

            // Registered back to front: each path is prepended, so reversing here
            // leaves them in the finder in the order getModuleViewPaths() lists
            // them. Without it a theme's root — which only holds the stub PHP
            // templates WordPress needs to consider the theme valid — would
            // outrank resources/views and shadow every Blade view of the theme.
            foreach (array_reverse($viewPaths) as $viewPath) {
                if (is_dir($viewPath)) {
                    // Add module views with high priority (prepend to search paths)
                    $this->registerViewPathWithPriority($viewFinder, $viewPath);
                }
            }

            // Register view namespace if module has a slug
            if ($moduleSlug && is_dir($modulePath.'/resources/views')) {
                $viewFactory->addNamespace($moduleSlug, $modulePath.'/resources/views');
            }

        } catch (\Throwable $throwable) {
            $this->logger?->error(sprintf('Failed to register view paths for module %s (%s)', $modulePath, $moduleType), ['exception' => $throwable]);
        }
    }

    /**
     * Get all possible view paths for a module.
     *
     * @param  string  $modulePath  Path to the module
     * @param  string  $moduleType  Type of module (theme, plugin, etc.)
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

    /**
     * Register a view path with high priority for module views.
     *
     * Adds the module view path to the beginning of the view finder's path list,
     * ensuring module views (including error views) are discovered before
     * framework defaults. This enables modules to override default error pages.
     *
     * @param  ViewFinderInterface  $viewFinder  The view finder instance
     * @param  string  $viewPath  Path to add with high priority
     */
    protected function registerViewPathWithPriority(ViewFinderInterface $viewFinder, string $viewPath): void
    {
        try {
            // Get current paths to preserve order
            /** @var FileViewFinder $viewFinder */
            $currentPaths = $viewFinder->getPaths();

            // Check if path is already registered to avoid duplicates
            if (! in_array($viewPath, $currentPaths, true)) {
                // Add the new path at the beginning for high priority
                $newPaths = array_merge([$viewPath], $currentPaths);

                // Use reflection to set the paths directly since there's no public method
                $reflection = new \ReflectionClass($viewFinder);

                if ($reflection->hasProperty('paths')) {
                    $pathsProperty = $reflection->getProperty('paths');
                    $pathsProperty->setValue($viewFinder, $newPaths);
                } else {
                    // Fallback to standard addLocation if reflection fails
                    $viewFinder->addLocation($viewPath);
                }
            }
        } catch (\Throwable) {
            // Fallback to standard registration if priority registration fails
            try {
                $viewFinder->addLocation($viewPath);
            } catch (\Throwable $throwable) {
                $this->logger?->error(sprintf('Failed to register view path with priority: %s', $viewPath), ['exception' => $throwable]);
            }
        }
    }
}
