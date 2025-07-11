<?php

declare(strict_types=1);

namespace Pollora\Plugin\Domain\Support;

use Illuminate\Support\Collection;
use Pollora\Plugin\Domain\Contracts\PluginModuleInterface;

/**
 * Collection class for managing plugin modules.
 *
 * Provides essential methods for working with collections of plugin modules.
 * Can be extended with additional methods as needed.
 */
class PluginCollection extends Collection
{
    /**
     * Filter plugins by active status.
     *
     * @return static Collection of active plugins
     */
    public function active(): static
    {
        return $this->filter(function (PluginModuleInterface $plugin): bool {
            return $plugin->isActive();
        });
    }

    /**
     * Filter plugins by inactive status.
     *
     * @return static Collection of inactive plugins
     */
    public function inactive(): static
    {
        return $this->filter(function (PluginModuleInterface $plugin): bool {
            return ! $plugin->isActive();
        });
    }

    /**
     * Filter plugins by enabled status.
     *
     * @return static Collection of enabled plugins
     */
    public function enabled(): static
    {
        return $this->filter(function (PluginModuleInterface $plugin): bool {
            return $plugin->isEnabled();
        });
    }

    /**
     * Filter plugins by disabled status.
     *
     * @return static Collection of disabled plugins
     */
    public function disabled(): static
    {
        return $this->filter(function (PluginModuleInterface $plugin): bool {
            return $plugin->isDisabled();
        });
    }

    /**
     * Sort plugins by name.
     *
     * @param  string  $direction  Sort direction ('asc' or 'desc')
     * @return static Sorted collection
     */
    public function sortByName(string $direction = 'asc'): static
    {
        return $this->sortBy(function (PluginModuleInterface $plugin): string {
            return $plugin->getName();
        }, SORT_REGULAR, $direction === 'desc');
    }

    /**
     * Find a plugin by name.
     *
     * @param  string  $name  Plugin name
     * @return PluginModuleInterface|null Plugin module or null if not found
     */
    public function findByName(string $name): ?PluginModuleInterface
    {
        return $this->first(function (PluginModuleInterface $plugin) use ($name): bool {
            return $plugin->getName() === $name;
        });
    }

    /**
     * Find a plugin by slug.
     *
     * @param  string  $slug  Plugin slug
     * @return PluginModuleInterface|null Plugin module or null if not found
     */
    public function findBySlug(string $slug): ?PluginModuleInterface
    {
        return $this->first(function (PluginModuleInterface $plugin) use ($slug): bool {
            return $plugin->getSlug() === $slug;
        });
    }
}
