<?php

declare(strict_types=1);

namespace Pollora\Discoverer\Scouts;

use Pollora\Discoverer\Infrastructure\Services\AbstractPolloraScout;
use Pollora\Hook\Domain\Contracts\Hooks;
use Spatie\StructureDiscoverer\Discover;

/**
 * Scout for discovering WordPress hook classes.
 *
 * This scout finds all classes that implement the Hooks interface,
 * typically located in Domain/Hooks directories within modules,
 * themes, and the main application.
 */
final class HookClassesScout extends AbstractPolloraScout
{
    /**
     * {@inheritDoc}
     */
    protected function criteria(Discover $discover): Discover
    {
        return $discover
            ->classes()
            ->implementing(Hooks::class);
    }

    /**
     * {@inheritDoc}
     */
    protected function getDefaultDirectories(): array
    {
        $paths = [];

        // Add main application path
        if ($appPath = $this->getAppPath()) {
            $paths[] = $appPath;
        }

        // Add module hook directories
        $paths = array_merge($paths, $this->getModuleHookPaths());

        // Add theme hook directories
        $paths = array_merge($paths, $this->getThemeHookPaths());

        return array_unique(array_filter($paths));
    }

    /**
     * Get hook directories from enabled Laravel modules.
     *
     * @return array<string> Array of module hook paths
     */
    private function getModuleHookPaths(): array
    {
        if (! $this->container->bound('modules')) {
            return [];
        }

        try {
            /** @var \Nwidart\Modules\Contracts\RepositoryInterface $modules */
            $modules = $this->container->make('modules');

            $paths = [];

            foreach ($modules->allEnabled() as $module) {
                // check app/Hooks in modules
                $appHookPath = $module->getPath().'/app/';
                if (is_dir($appHookPath)) {
                    $paths[] = $appHookPath;
                }
            }

            return $paths;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Get hook directories from WordPress themes.
     *
     * @return array<string> Array of theme hook paths
     */
    private function getThemeHookPaths(): array
    {
        $paths = [];

        foreach ($this->getThemePaths() as $themePath) {
            // Common hook directory patterns in themes
            $hookPaths = [
                $themePath.'/inc/Hooks',
                $themePath.'/app/Hooks',
                $themePath.'/Hooks',
            ];

            foreach ($hookPaths as $hookPath) {
                if (is_dir($hookPath)) {
                    $paths[] = $hookPath;
                }
            }
        }

        return $paths;
    }
}
