<?php

declare(strict_types=1);

namespace Pollora\Discoverer\Infrastructure\Services;

use Illuminate\Contracts\Container\Container;
use Pollora\Application\Domain\Contracts\DebugDetectorInterface;
use Pollora\Discoverer\Domain\Contracts\ScoutInterface;
use Spatie\StructureDiscoverer\Cache\DiscoverCacheDriver;
use Spatie\StructureDiscoverer\Discover;

/**
 * Adapter for Spatie's structure-discoverer package.
 */
abstract class SpatieDiscoveryAdapter implements ScoutInterface
{
    /**
     * The Laravel application container.
     */
    protected Container $app;

    protected ?DiscoverCacheDriver $cacheDriver;

    protected ?DebugDetectorInterface $debugDetector;

    /**
     * Constructor.
     *
     * @param  Container  $app  The Laravel application container
     */
    public function __construct(Container $app)
    {
        $this->app = $app;
        $this->cacheDriver = $app->has(DiscoverCacheDriver::class) ? $app->make(DiscoverCacheDriver::class) : null;
        $this->debugDetector = $app->has(DebugDetectorInterface::class) ? $app->make(DebugDetectorInterface::class) : null;
    }

    /**
     * Get the directories to scan.
     *
     * @return array<string> Directory or array of directories to scan
     */
    abstract public function getDirectories(): array;

    /**
     * Get the type identifier for discovered classes.
     */
    abstract public function getType(): string;

    /**
     * Define the discovery criteria.
     *
     * @param  Discover  $discover  Discovery instance
     * @return Discover Modified discovery instance
     */
    abstract protected function criteria(Discover $discover): Discover;

    /**
     * Get cache identifier for this scout.
     */
    protected function getCacheIdentifier(): string
    {
        return 'pollora_scout_'.$this->getType();
    }

    /**
     * Get whether caching should be enabled.
     */
    protected function shouldUseCache(): bool
    {
        // Use the debug detector if available
        if ($this->debugDetector !== null) {
            return ! $this->debugDetector->isDebugMode();
        }

        // Fallback to direct WP_DEBUG check
        if (defined('WP_DEBUG') && WP_DEBUG) {
            return false;
        }

        return true;
    }

    /**
     * Discover classes using Spatie's discovery engine.
     *
     * @return array<string> Array of discovered class names
     */
    public function discover(): array
    {
        $directories = $this->getDirectories();
        $validDirectories = array_filter($directories, 'is_dir');

        if (empty($validDirectories)) {
            return [];
        }

        try {
            $discover = Discover::in(...$validDirectories);

            // Apply criteria
            $discover = $this->criteria($discover);

            // Apply caching if enabled
            if ($this->cacheDriver !== null && $this->shouldUseCache()) {
                $discover = $discover->withCache(
                    $this->getCacheIdentifier(),
                    $this->cacheDriver
                );
            }

            return $discover->get();
        } catch (\Exception $e) {
            // Log error and return empty array
            return [];
        }
    }
}
