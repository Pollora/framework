<?php

declare(strict_types=1);

namespace Pollora\Modules\Infrastructure\Services;

use Illuminate\Container\Container;
use Pollora\Discovery\Application\Services\DiscoveryManager;
use Pollora\Discovery\Domain\Contracts\DiscoveryEngineInterface;
use Pollora\Discovery\Domain\Models\DirectoryLocation;
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
        // Convert scout class to discovery type mapping
        $discoveryType = $this->mapScoutClassToDiscoveryType($scoutClass);
        
        if (!$discoveryType) {
            if (function_exists('error_log')) {
                error_log("Unknown scout class, skipping discovery: {$scoutClass}");
            }
            return;
        }

        try {
            $this->performDiscoveryInPath($path, $discoveryType);
        } catch (\Throwable $e) {
            if (function_exists('error_log')) {
                error_log("Discovery error for path {$path}: " . $e->getMessage());
            }
        }
    }

    /**
     * {@inheritDoc}
     */
    public function discoverModule(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        try {
            // Run discovery on the module path for all discovery types
            $this->performDiscoveryInPath($path, 'all');
        } catch (\Throwable $e) {
            if (function_exists('error_log')) {
                error_log("Module discovery error for path {$path}: " . $e->getMessage());
            }
        }
    }

    /**
     * {@inheritDoc}
     */
    public function discoverTheme(string $themePath): void
    {
        $this->discoverModule($themePath);
    }

    /**
     * {@inheritDoc}
     */
    public function discoverPlugin(string $pluginPath): void
    {
        $this->discoverModule($pluginPath);
    }

    /**
     * {@inheritDoc}
     */
    public function discoverAllInPath(string $path): array
    {
        if (!is_dir($path) || !$this->container->bound(DiscoveryManager::class)) {
            return [];
        }

        try {
            /** @var DiscoveryManager $manager */
            $manager = $this->container->make(DiscoveryManager::class);
            
            $location = new DirectoryLocation($path);
            return $manager->discoverAllInLocation($location);
        } catch (\Throwable $e) {
            if (function_exists('error_log')) {
                error_log("Discovery error for path {$path}: " . $e->getMessage());
            }
            return [];
        }
    }

    /**
     * Perform discovery in a specific path.
     *
     * @param string $path The path to discover in
     * @param string $discoveryType The type of discovery to perform ('all' or specific type)
     */
    protected function performDiscoveryInPath(string $path, string $discoveryType): void
    {
        if (!$this->container->bound(DiscoveryEngineInterface::class)) {
            return;
        }

        /** @var DiscoveryEngineInterface $engine */
        $engine = $this->container->make(DiscoveryEngineInterface::class);
        
        // Create a temporary location for this path
        $location = new DirectoryLocation($path);
        
        if ($discoveryType === 'all') {
            // Run all discoveries
            $engine->addLocation($location)->discover()->apply();
        } else {
            // Run specific discovery type if available
            if ($this->container->bound(DiscoveryManager::class)) {
                /** @var DiscoveryManager $manager */
                $manager = $this->container->make(DiscoveryManager::class);
                
                if ($manager->hasDiscovery($discoveryType)) {
                    $manager->runSpecificDiscovery($discoveryType, $location);
                }
            }
        }
    }

    /**
     * Map old scout class names to new discovery types.
     *
     * @param string $scoutClass The old scout class name
     * @return string|null The corresponding discovery type or null if unknown
     */
    protected function mapScoutClassToDiscoveryType(string $scoutClass): ?string
    {
        $mappings = [
            'ServiceProviderScout' => 'service_providers',
            'PostTypeClassesScout' => 'post_types', 
            'TaxonomyClassesScout' => 'taxonomies',
            'HookClassesScout' => 'hooks',
            'WpRestRoutesScout' => 'wp_rest_routes',
            'AttributableClassesScout' => 'attributable',
            'ScheduleClassesScout' => 'schedules',
        ];

        foreach ($mappings as $scoutName => $discoveryType) {
            if (str_contains($scoutClass, $scoutName)) {
                return $discoveryType;
            }
        }

        return null;
    }
}
