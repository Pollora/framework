<?php

declare(strict_types=1);

namespace Pollora\Discoverer\Scouts;

use Pollora\Discoverer\Infrastructure\Services\AbstractPolloraScout;
use Pollora\Taxonomy\Domain\Models\AbstractTaxonomy;
use Spatie\StructureDiscoverer\Discover;

/**
 * Scout for discovering custom taxonomy classes.
 *
 * This scout finds all classes that extend AbstractTaxonomy across
 * the application, modules, themes, and plugins for automatic
 * WordPress taxonomy registration.
 */
final class TaxonomyClassesScout extends AbstractPolloraScout
{
    /**
     * {@inheritDoc}
     */
    protected function criteria(Discover $discover): Discover
    {
        return $discover
            ->classes()
            ->extending(AbstractTaxonomy::class);
    }

    /**
     * {@inheritDoc}
     */
    protected function getDefaultDirectories(): array
    {
        $paths = parent::getDefaultDirectories();

        // Add plugin paths for WordPress environments
        $paths = array_merge($paths, $this->getPluginPaths());

        // Add specific taxonomy directories
        $paths = array_merge($paths, $this->getTaxonomyDirectories());

        return array_unique(array_filter($paths));
    }

    /**
     * Get common taxonomy directories from modules and themes.
     *
     * @return array<string> Array of taxonomy specific paths
     */
    private function getTaxonomyDirectories(): array
    {
        $paths = [];

        // Module taxonomy directories
        if ($this->container->bound('modules')) {
            try {
                /** @var \Nwidart\Modules\Contracts\RepositoryInterface $modules */
                $modules = $this->container->make('modules');

                foreach ($modules->allEnabled() as $module) {
                    $taxonomyPaths = [
                        $module->getPath().'/app',
                    ];

                    foreach ($taxonomyPaths as $path) {
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
