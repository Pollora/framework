<?php

declare(strict_types=1);

namespace Pollora\Discovery\Domain\Contracts;

/**
 * Contract for discovery services that support the configuring lifecycle hook.
 *
 * Discovery services implementing this interface allow discovered classes
 * to interact with the entity before final registration.
 */
interface ConfigurableDiscoveryInterface
{
    /**
     * Create an entity instance for the configuring method.
     *
     * This entity will be passed to the configuring method of discovered classes.
     *
     * @param  string  $slug  The entity slug
     * @param  string|null  $singular  The singular name
     * @param  string|null  $plural  The plural name
     * @param  array  $args  Additional arguments
     * @param  int  $priority  Declaration priority
     * @return object The created entity (PostType, Taxonomy, etc.)
     */
    public function createEntityForConfiguring(string $slug, ?string $singular = null, ?string $plural = null, array $args = [], int $priority = 5): object;
}
