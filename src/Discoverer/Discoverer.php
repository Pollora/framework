<?php

declare(strict_types=1);

namespace Pollora\Discoverer;

use Pollora\Discoverer\Contracts\DiscoveryRegistry;
use Pollora\Discoverer\Scouts\AbstractScout;
use Spatie\StructureDiscoverer\Discover;

/**
 * Main class discovery manager.
 *
 * Handles all class discovery operations using Spatie's structure-discoverer package.
 * Provides a centralized interface for discovering classes based on various criteria.
 */
class Discoverer
{
    /**
     * @var array<string, bool> Cache of discovered class status
     */
    protected array $discoveredCache = [];

    /**
     * @param DiscoveryRegistry $registry Registry for discovered classes
     */
    public function __construct(
        protected readonly DiscoveryRegistry $registry
    ) {
    }

    /**
     * Discover classes based on criteria.
     *
     * @param string|array<string> $directories Directory or directories to scan
     * @param callable $criteriaCallback Callback to configure discovery criteria
     * @return array<string> Array of discovered class names
     */
    public function discover(string|array $directories, callable $criteriaCallback): array
    {
        // Ensure directories is an array
        $dirs = is_array($directories) ? $directories : [$directories];

        // Filter valid directories
        $validDirs = array_filter($dirs, function($dir) {
            return is_dir($dir);
        });

        if (empty($validDirs)) {
            return [];
        }

        // Let the Spatie package handle the discovery
        // Caching will be applied automatically based on the config
        $discoverer = Discover::in(...$validDirs);

        // Apply criteria
        return $criteriaCallback($discoverer)->get();
    }

    /**
     * Discover classes implementing a specific interface.
     *
     * @param string|array<string> $directories Directory or directories to scan
     * @param string $interface Interface class name
     * @return array<string> Array of discovered class names
     */
    public function discoverImplementing(string|array $directories, string $interface): array
    {
        return $this->discover($directories, function ($discoverer) use ($interface) {
            return $discoverer->implementing($interface);
        });
    }

    /**
     * Discover classes extending a specific class.
     *
     * @param string|array<string> $directories Directory or directories to scan
     * @param string $baseClass Base class name
     * @return array<string> Array of discovered class names
     */
    public function discoverExtending(string|array $directories, string $baseClass): array
    {
        return $this->discover($directories, function ($discoverer) use ($baseClass) {
            return $discoverer->extending($baseClass);
        });
    }

    /**
     * Discover classes using a specific attribute.
     *
     * @param string|array<string> $directories Directory or directories to scan
     * @param string $attribute Attribute class name
     * @return array<string> Array of discovered class names
     */
    public function discoverWithAttribute(string|array $directories, string $attribute): array
    {
        return $this->discover($directories, function ($discoverer) use ($attribute) {
            return $discoverer->withAttribute($attribute);
        });
    }

    /**
     * Register discovered classes with the registry.
     *
     * @param array<string> $classes Array of class names
     * @param string $type Type identifier for the discovered classes
     * @return void
     */
    public function registerDiscovered(array $classes, string $type): void
    {
        foreach ($classes as $class) {
            if (!isset($this->discoveredCache[$class])) {
                $this->registry->register($class, $type);
                $this->discoveredCache[$class] = true;
            }
        }
    }
}
