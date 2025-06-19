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
    public function __construct(
        protected Container $container
    ) {}

    /**
     * {@inheritDoc}
     */
    public function discoverInPath(string $path, string $scoutClass): void
    {
        // Validate scout class
        if (! is_subclass_of($scoutClass, AbstractPolloraScout::class)) {
            throw new \InvalidArgumentException('Scout class must extend AbstractPolloraScout');
        }

        // Create scout instance with specific directory
        $scout = new $scoutClass($this->container, [$path]);

        // Perform discovery
        $scout->handle();
    }

    /**
     * {@inheritDoc}
     */
    public function discoverModule(string $path): void
    {
        $scoutClasses = $this->getAvailableScouts();
        foreach ($scoutClasses as $scoutClass) {
            $this->discoverInPath($path, $scoutClass);
        }
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
}
