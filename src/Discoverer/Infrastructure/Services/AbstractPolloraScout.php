<?php

declare(strict_types=1);

namespace Pollora\Discoverer\Infrastructure\Services;

use Illuminate\Container\Container;
use Spatie\StructureDiscoverer\Discover;
use Spatie\StructureDiscoverer\StructureScout;

/**
 * Abstract base class for Pollora scouts providing enhanced path management.
 *
 * This class extends StructureScout to provide intelligent path detection
 * for Laravel applications, WordPress themes/plugins, and Laravel modules.
 * It handles environment detection and provides flexible directory configuration.
 */
abstract class AbstractPolloraScout extends StructureScout
{
    /**
     * Cache for theme paths to avoid repeated WordPress function calls.
     *
     * @var array<string>|null
     */
    private ?array $cachedThemePaths = null;

    /**
     * @param  Container  $container  Laravel container for dependency injection
     * @param  array<string>  $directories  Custom directories to scan (optional)
     */
    public function __construct(
        protected Container $container,
        protected array $directories = []
    ) {
        if (empty($this->directories)) {
            $this->directories = $this->getDefaultDirectories();
        }
    }

    /**
     * {@inheritDoc}
     */
    protected function definition(): Discover
    {
        $validDirectories = $this->getValidDirectories();

        if (empty($validDirectories)) {
            // Return discovery with temp directory to avoid errors
            $discover = Discover::in(sys_get_temp_dir());
        } else {
            $discover = Discover::in(...$validDirectories);
        }

        // Apply parallel processing for better performance on large codebases
        $discover = $discover->parallel(100);

        // Apply scout-specific discovery criteria
        return $this->criteria($discover);
    }

    /**
     * Apply scout-specific discovery criteria to the Discover instance.
     *
     * @param  Discover  $discover  The base discover instance
     * @return Discover The configured discover instance
     */
    abstract protected function criteria(Discover $discover): Discover;

    /**
     * Get the default directories to scan for this scout.
     *
     * Note: Theme paths are not included here as they require WordPress functions
     * that are only available during the boot phase. They are lazy-loaded in getValidDirectories().
     *
     * @return array<string> Array of directories to scan
     */
    protected function getDefaultDirectories(): array
    {
        return array_filter([
            $this->getAppPath(),
            ...$this->getModulePaths(),
            // Theme paths are added later via lazy loading in getValidDirectories()
        ]);
    }

    /**
     * Get valid, existing directories from the configured list.
     *
     * This method includes lazy-loaded theme paths that are only available
     * after WordPress has been initialized.
     *
     * @return array<string> Array of existing directories
     */
    protected function getValidDirectories(): array
    {
        // Merge base directories with lazy-loaded theme paths
        $allDirectories = array_merge(
            $this->directories,
            $this->getThemePathsLazy()
        );

        return array_filter(array_unique($allDirectories), function (string $directory): bool {
            return is_dir($directory) && is_readable($directory);
        });
    }

    /**
     * Get the Laravel application path.
     *
     * @return string|null The app path or null if not available
     */
    protected function getAppPath(): ?string
    {
        return function_exists('app_path') ? app_path() : null;
    }

    /**
     * Get paths for all enabled Laravel modules.
     *
     * @return array<string> Array of module paths
     */
    protected function getModulePaths(): array
    {
        if (! $this->container->bound('modules')) {
            return [];
        }

        try {
            /** @var \Nwidart\Modules\Contracts\RepositoryInterface $modules */
            $modules = $this->container->make('modules');

            $paths = [];

            // Add base modules path
            if (method_exists($modules, 'getPath')) {
                $basePath = $modules->getPath();
                if ($basePath && is_dir($basePath)) {
                    $paths[] = $basePath;
                }
            }

            // Add individual enabled module paths
            foreach ($modules->allEnabled() as $module) {
                $modulePath = $module->getPath();
                if ($modulePath && is_dir($modulePath)) {
                    $paths[] = $modulePath;
                }
            }

            return array_unique($paths);
        } catch (\Throwable) {
            // Ignore errors and return empty array
            return [];
        }
    }

    /**
     * Get WordPress theme paths with lazy loading and caching.
     *
     * This method is safe to call during any phase as it only retrieves
     * theme paths when WordPress functions are available.
     *
     * @return array<string> Array of theme paths
     */
    protected function getThemePathsLazy(): array
    {
        // Return cached paths if available
        if ($this->cachedThemePaths !== null) {
            return $this->cachedThemePaths;
        }

        // Only attempt to get theme paths if WordPress functions are available
        if (! function_exists('get_stylesheet_directory') || ! function_exists('get_template_directory')) {
            $this->cachedThemePaths = [];

            return $this->cachedThemePaths;
        }

        $paths = [];

        // Add active theme (child theme) path
        $stylesheetDir = get_stylesheet_directory();
        if ($stylesheetDir && is_dir($stylesheetDir)) {
            $paths[] = $stylesheetDir;
        }

        // Add parent theme path
        $templateDir = get_template_directory();
        if ($templateDir && is_dir($templateDir) && $templateDir !== $stylesheetDir) {
            $paths[] = $templateDir;
        }

        // Cache the result
        $this->cachedThemePaths = $paths;

        return $this->cachedThemePaths;
    }

    /**
     * Get WordPress theme paths (active theme and parent theme).
     *
     * @deprecated Use getThemePathsLazy() instead for safe lazy loading
     *
     * @return array<string> Array of theme paths
     */
    protected function getThemePaths(): array
    {
        return $this->getThemePathsLazy();
    }

    /**
     * Get WordPress plugin paths.
     *
     * @return array<string> Array of plugin paths
     */
    protected function getPluginPaths(): array
    {
        if (! defined('WP_PLUGIN_DIR') || ! is_dir(WP_PLUGIN_DIR)) {
            return [];
        }

        return [WP_PLUGIN_DIR];
    }

    /**
     * Get a custom cache identifier that includes directory information.
     *
     * This ensures cache invalidation when directories change.
     *
     * @return string The cache identifier
     */
    public function identifier(): string
    {
        $baseIdentifier = parent::identifier();
        $directoryHash = md5(serialize($this->getValidDirectories()));

        return $baseIdentifier.'_'.$directoryHash;
    }
}
