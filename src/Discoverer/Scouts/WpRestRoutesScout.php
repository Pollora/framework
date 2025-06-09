<?php

declare(strict_types=1);

namespace Pollora\Discoverer\Scouts;

use Pollora\Discoverer\Infrastructure\Services\AbstractPolloraScout;
use Pollora\WpRest\AbstractWpRestRoute;
use Spatie\StructureDiscoverer\Discover;

/**
 * Scout for discovering WordPress REST API route classes.
 *
 * This scout finds all classes that extend AbstractWpRestRoute across
 * the application, modules, themes, and plugins for automatic
 * WordPress REST API endpoint registration.
 */
final class WpRestRoutesScout extends AbstractPolloraScout
{
    /**
     * {@inheritDoc}
     */
    protected function criteria(Discover $discover): Discover
    {
        return $discover
            ->classes()
            ->extending(AbstractWpRestRoute::class);
    }

    /**
     * {@inheritDoc}
     */
    protected function getDefaultDirectories(): array
    {
        $paths = parent::getDefaultDirectories();

        // Add plugin paths for WordPress environments
        $paths = array_merge($paths, $this->getPluginPaths());

        // Add specific REST route directories
        $paths = array_merge($paths, $this->getRestRouteDirectories());

        return array_unique(array_filter($paths));
    }

    /**
     * Get common REST route directories from modules and themes.
     *
     * @return array<string> Array of REST route specific paths
     */
    private function getRestRouteDirectories(): array
    {
        $paths = [];

        // Module REST route directories
        if ($this->container->bound('modules')) {
            try {
                /** @var \Nwidart\Modules\Contracts\RepositoryInterface $modules */
                $modules = $this->container->make('modules');

                foreach ($modules->allEnabled() as $module) {
                    $routePaths = [
                        $module->getPath().'/app',
                    ];

                    foreach ($routePaths as $path) {
                        if (is_dir($path)) {
                            $paths[] = $path;
                        }
                    }
                }
            } catch (\Throwable) {
                // Continue silently
            }
        }

        // Theme REST route directories
        foreach ($this->getThemePaths() as $themePath) {
            $routePaths = [
                $themePath.'/app',
            ];

            foreach ($routePaths as $path) {
                if (is_dir($path)) {
                    $paths[] = $path;
                }
            }
        }

        return $paths;
    }
}
