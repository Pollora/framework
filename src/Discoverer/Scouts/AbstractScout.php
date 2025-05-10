<?php

declare(strict_types=1);

namespace Pollora\Discoverer\Scouts;

use Spatie\StructureDiscoverer\Cache\DiscoverCacheDriver;
use Spatie\StructureDiscoverer\Cache\FileDiscoverCacheDriver;
use Spatie\StructureDiscoverer\Cache\NullDiscoverCacheDriver;
use Spatie\StructureDiscoverer\Discover;
use Spatie\StructureDiscoverer\DiscoverConditionFactory;
use Spatie\StructureDiscoverer\StructureScout;

/**
 * Abstract base class for all discovery scouts.
 *
 * Scouts are responsible for defining discovery rules and criteria
 * for specific types of classes within the application.
 */
abstract class AbstractScout extends StructureScout
{
    /**
     * Get the directories to scan.
     *
     * @return string|array<string> Directory or array of directories to scan
     */
    abstract protected function directory(): string|array;

    /**
     * Get the type identifier for discovered classes.
     *
     * @return string Type identifier
     */
    abstract protected function type(): string;

    /**
     * Get the type identifier for discovered classes (public accessor).
     *
     * @return string Type identifier
     */
    public function getType(): string
    {
        return $this->type();
    }

    /**
     * Define the discovery criteria.
     *
     * @param  Discover|DiscoverConditionFactory  $discover  Discover instance
     * @return Discover|DiscoverConditionFactory Configured discover instance
     */
    abstract protected function criteria(Discover|DiscoverConditionFactory $discover): Discover|DiscoverConditionFactory;

    /**
     * Discover classes based on the scout's criteria.
     *
     * @return Discover Array of discovered class names
     */
    public function definition(): Discover
    {
        // Standardize directory/directories to an array
        $directories = is_array($this->directory())
            ? $this->directory()
            : [$this->directory()];

        // Filter out non-existent directories
        $validDirectories = array_filter($directories, function ($dir) {
            return is_dir($dir);
        });

        // If no valid directories, return an empty array
        if (empty($validDirectories)) {
            return [];
        }

        try {
            // Start discovery process with all valid directories
            // Spatie will handle the caching automatically based on the config
            $discover = Discover::in(...$validDirectories);
            $discover = $this->criteria($discover);

            $result = $discover;

            return $result;
        } catch (\Exception $e) {
            \Log::error('Error in scout '.static::class.': '.$e->getMessage());

            return [];
        }
    }

    public function cacheDriver(): DiscoverCacheDriver
    {
        return app()->isLocal() || config('app.debug') ? new NullDiscoverCacheDriver : new FileDiscoverCacheDriver(storage_path('discoverer'));
    }
}
