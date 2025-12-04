<?php

declare(strict_types=1);

namespace Pollora\PostType\Application\Services;

use Pollora\PostType\Domain\Contracts\PostTypeFactoryInterface;
use Pollora\PostType\Domain\Contracts\PostTypeRegistryInterface;
use Pollora\PostType\Domain\Contracts\PostTypeServiceInterface;

/**
 * Application service for post type management.
 *
 * This service orchestrates the creation and registration of post types
 * following hexagonal architecture principles and implementing the common interface.
 */
class PostTypeService implements PostTypeServiceInterface
{
    /**
     * Create a new PostTypeService instance.
     */
    public function __construct(
        private readonly PostTypeFactoryInterface $factory,
        private readonly PostTypeRegistryInterface $registry
    ) {}

    /**
     * Create a new post type instance.
     *
     * @param  string  $slug  The post type slug
     * @param  string|null  $singular  The singular label for the post type
     * @param  string|null  $plural  The plural label for the post type
     * @param  array<string, mixed>  $args  Additional arguments
     * @param  int  $priority  Declaration priority
     * @return object The created post type instance
     */
    public function create(string $slug, ?string $singular = null, ?string $plural = null, array $args = [], int $priority = 5): object
    {
        return $this->factory->make($slug, $singular, $plural, $args, $priority);
    }

    /**
     * Register a new post type.
     *
     * @param  string  $slug  The post type slug
     * @param  string|null  $singular  The singular label for the post type
     * @param  string|null  $plural  The plural label for the post type
     * @param  array<string, mixed>  $args  Additional arguments
     * @param  int  $priority  Declaration priority
     * @return object The registered post type instance
     */
    public function register(string $slug, ?string $singular = null, ?string $plural = null, array $args = [], int $priority = 5): object
    {
        // The factory creates and registers the post type (pollora/entity handles register_post_type)
        return $this->factory->make($slug, $singular, $plural, $args, $priority);
    }

    /**
     * Check if a post type exists.
     *
     * @param  string  $slug  The post type slug to check
     * @return bool True if the post type exists
     */
    public function exists(string $slug): bool
    {
        return $this->registry->exists($slug);
    }

    /**
     * Get all registered post types.
     *
     * @return array<string, mixed> The registered post types
     */
    public function getRegistered(): array
    {
        return $this->registry->getAll();
    }
}
