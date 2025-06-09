<?php

declare(strict_types=1);

namespace Pollora\Discoverer\Scouts;

use Pollora\Discoverer\Infrastructure\Services\AbstractPolloraScout;
use Pollora\PostType\Domain\Models\AbstractPostType;
use Spatie\StructureDiscoverer\Discover;

/**
 * Scout for discovering custom post type classes.
 *
 * This scout finds all classes that extend AbstractPostType across
 * the application, modules, themes, and plugins for automatic
 * WordPress post type registration.
 */
final class PostTypeClassesScout extends AbstractPolloraScout
{
    /**
     * {@inheritDoc}
     */
    protected function criteria(Discover $discover): Discover
    {
        return $discover
            ->classes()
            ->extending(AbstractPostType::class);
    }

    /**
     * {@inheritDoc}
     */
    protected function getDefaultDirectories(): array
    {
        $paths = parent::getDefaultDirectories();

        // Add plugin paths for WordPress environments
        $paths = array_merge($paths, $this->getPluginPaths());

        // Add specific post type directories
        $paths = array_merge($paths, $this->getPostTypeDirectories());

        return array_unique(array_filter($paths));
    }

    /**
     * Get common post type directories from modules and themes.
     *
     * @return array<string> Array of post type specific paths
     */
    private function getPostTypeDirectories(): array
    {
        $paths = [];

        // Module post type directories
        if ($this->container->bound('modules')) {
            try {
                /** @var \Nwidart\Modules\Contracts\RepositoryInterface $modules */
                $modules = $this->container->make('modules');

                foreach ($modules->allEnabled() as $module) {
                    $postTypePaths = [
                        $module->getPath().'/app',
                    ];

                    foreach ($postTypePaths as $path) {
                        if (is_dir($path)) {
                            $paths[] = $path;
                        }
                    }
                }
            } catch (\Throwable) {
                // Continue silently
            }
        }

        return $paths;
    }
}
