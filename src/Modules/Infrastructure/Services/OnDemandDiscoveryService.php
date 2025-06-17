<?php

declare(strict_types=1);

namespace Pollora\Modules\Infrastructure\Services;

use Illuminate\Container\Container;
use Pollora\Discoverer\Infrastructure\Services\AbstractPolloraScout;
use Pollora\Modules\Domain\Contracts\OnDemandDiscoveryInterface;

/**
 * Service for on-demand discovery of structures in modules, themes and plugins.
 *
 * This service allows discovery to be triggered at the moment a module, theme or plugin
 * is registered, avoiding the early execution cycle problem where directories
 * are not yet available.
 */
class OnDemandDiscoveryService implements OnDemandDiscoveryInterface
{
    /**
     * Cache for discovered structures per path to avoid re-scanning.
     */
    private array $discoveryCache = [];

    public function __construct(
        protected Container $container
    ) {}

    /**
     * {@inheritDoc}
     */
    public function discoverInPath(string $path, string $scoutClass): void
    {
        // Validate scout class
        if (!is_subclass_of($scoutClass, AbstractPolloraScout::class)) {
            throw new \InvalidArgumentException("Scout class must extend AbstractPolloraScout");
        }

        // Create scout instance with specific directory
        $scout = new $scoutClass($this->container, [$path]);

        // Perform discovery
        $scout->handle();
    }

    /**
     * {@inheritDoc}
     */
    public function discoverAllInPath(string $path): void
    {
        $scoutClasses = $this->getAvailableScouts();
        foreach ($scoutClasses as $scoutType => $scoutClass) {
            $this->discoverInPath($path, $scoutClass);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function discoverModule(string $modulePath, ?callable $processor = null): void
    {
        $this->discoverAllInPath($modulePath);
    }

    /**
     * {@inheritDoc}
     */
    public function discoverTheme(string $themePath, ?callable $processor = null): void
    {
        $this->discoverModule($themePath, $processor);
    }

    /**
     * {@inheritDoc}
     */
    public function discoverPlugin(string $pluginPath, ?callable $processor = null): void
    {
        $this->discoverModule($pluginPath, $processor);
    }

    /**
     * Get available scout classes.
     *
     * @return array<string, class-string<AbstractPolloraScout>>
     */
    protected function getAvailableScouts(): array
    {
        return [
            'service_providers' => \Pollora\Discoverer\Scouts\ServiceProviderScout::class,
            'post_types' => \Pollora\Discoverer\Scouts\PostTypeClassesScout::class,
            'taxonomies' => \Pollora\Discoverer\Scouts\TaxonomyClassesScout::class,
            'hooks' => \Pollora\Discoverer\Scouts\HookClassesScout::class,
            'wp_rest_routes' => \Pollora\Discoverer\Scouts\WpRestRoutesScout::class,
            'attributable_classes' => \Pollora\Discoverer\Scouts\AttributableClassesScout::class,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function clearCache(): void
    {
        $this->discoveryCache = [];
    }

    /**
     * {@inheritDoc}
     */
    public function clearCacheForPath(string $path): void
    {
        $pathHash = md5($path);

        foreach (array_keys($this->discoveryCache) as $cacheKey) {
            if (str_contains($cacheKey, $pathHash)) {
                unset($this->discoveryCache[$cacheKey]);
            }
        }
    }
}
