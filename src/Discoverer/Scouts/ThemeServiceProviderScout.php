<?php

declare(strict_types=1);

namespace Pollora\Discoverer\Scouts;

use Illuminate\Support\ServiceProvider;
use Pollora\Discoverer\Infrastructure\Services\AbstractPolloraScout;
use Pollora\Modules\Domain\Contracts\ModuleRepositoryInterface;
use Spatie\StructureDiscoverer\Discover;

final class ThemeServiceProviderScout extends AbstractPolloraScout
{
    protected function criteria(Discover $discover): Discover
    {
        return $discover
            ->classes()
            ->extending(ServiceProvider::class);
    }

    protected function getDefaultDirectories(): array
    {
        $paths = [];

        // Get theme directories from the module repository
        if ($this->container->bound(ModuleRepositoryInterface::class)) {
            try {
                $repository = $this->container->make(ModuleRepositoryInterface::class);
                foreach ($repository->allEnabled() as $module) {
                    $modulePath = $module->getPath();
                    if ($this->isValidThemeDirectory($modulePath)) {
                        $paths[] = $modulePath;
                    }
                }
            } catch (\Exception $e) {
                // Silently continue if repository is not available
            }
        }

        // Fallback to WordPress theme paths if no modules found
        if (empty($paths)) {
            $paths = $this->getWordPressThemePaths();
        }

        return array_unique(array_filter($paths));
    }

    /**
     * Get WordPress theme paths using WordPress functions (fallback method).
     */
    private function getWordPressThemePaths(): array
    {
        $paths = [];

        // Use WordPress functions if available
        if (function_exists('get_theme_root')) {
            $themeRoot = get_theme_root();
            if (is_dir($themeRoot)) {
                $themeDirs = glob($themeRoot.'/*', GLOB_ONLYDIR);
                if ($themeDirs !== false) {
                    foreach ($themeDirs as $themeDir) {
                        if ($this->isValidThemeDirectory($themeDir)) {
                            $paths[] = $themeDir;
                        }
                    }
                }
            }
        }

        // Fallback to configuration or standard paths
        if (empty($paths)) {
            $fallbackPaths = [
                function_exists('config') ? config('modules.paths.modules') : null,
                base_path('themes'),
                public_path('themes'),
            ];

            foreach ($fallbackPaths as $fallbackPath) {
                if ($fallbackPath && is_dir($fallbackPath)) {
                    $themeDirs = glob($fallbackPath.'/*', GLOB_ONLYDIR);
                    if ($themeDirs !== false) {
                        foreach ($themeDirs as $themeDir) {
                            if ($this->isValidThemeDirectory($themeDir)) {
                                $paths[] = $themeDir;
                            }
                        }
                    }
                }
            }
        }

        return array_filter($paths);
    }

    /**
     * Validate that a theme directory contains valid theme files.
     */
    protected function isValidThemeDirectory(string $path): bool
    {
        if (!is_dir($path)) {
            return false;
        }

        // Check for WordPress theme requirements
        if (file_exists($path.'/style.css') && file_exists($path.'/index.php')) {
            return true;
        }

        // Check for Laravel module-style theme structure
        if (is_dir($path.'/app') || is_dir($path.'/src')) {
            return true;
        }

        return false;
    }
}
