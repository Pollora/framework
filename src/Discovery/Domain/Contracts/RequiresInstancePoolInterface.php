<?php

declare(strict_types=1);

namespace Pollora\Discovery\Domain\Contracts;

/**
 * Interface RequiresInstancePoolInterface
 *
 * Marker interface for Discovery implementations that require automatic
 * instance creation through the InstancePool. This allows the DiscoveryEngine
 * to optimize memory usage by only creating the InstancePool when needed.
 *
 * Discoveries that implement this interface will:
 * - Have access to the shared InstancePool for efficient instance management
 * - Automatically instantiate discovered classes when needed
 * - Benefit from circular dependency detection and caching
 *
 * Use cases:
 * - WP CLI command registration (WpCliDiscovery)
 * - Service provider auto-registration
 * - Event listener auto-wiring
 * - Any discovery that needs to create instances of discovered classes
 */
interface RequiresInstancePoolInterface
{
    // Marker interface - no methods required
}
