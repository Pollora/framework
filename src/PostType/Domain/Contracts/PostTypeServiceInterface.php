<?php

declare(strict_types=1);

namespace Pollora\PostType\Domain\Contracts;

/**
 * Interface for PostType application services.
 *
 * This interface defines the contract for managing post types across the application.
 * It provides methods for creating, registering, and querying post types while
 * maintaining separation of concerns between different implementations.
 */
interface PostTypeServiceInterface
{
    /**
     * Create a new post type instance.
     *
     * @param  string  $slug  The post type slug
     * @param  string|null  $singular  The singular label for the post type
     * @param  string|null  $plural  The plural label for the post type
     * @param  array<string, mixed>  $args  Additional arguments
     * @return object The created post type instance
     */
    public function create(string $slug, ?string $singular = null, ?string $plural = null, array $args = []): object;

    /**
     * Register a post type with the system.
     *
     * @param  string  $slug  The post type slug
     * @param  string|null  $singular  The singular label for the post type
     * @param  string|null  $plural  The plural label for the post type
     * @param  array<string, mixed>  $args  Additional arguments
     * @return object The registered post type instance
     */
    public function register(string $slug, ?string $singular = null, ?string $plural = null, array $args = []): object;

    /**
     * Check if a post type exists.
     *
     * @param  string  $slug  The post type slug to check
     * @return bool True if the post type exists
     */
    public function exists(string $slug): bool;

    /**
     * Get all registered post types.
     *
     * @return array<string, mixed> The registered post types
     */
    public function getRegistered(): array;

    /**
     * Register a post type instance.
     *
     * @param  object  $postType  The post type instance to register
     */
    public function registerInstance(object $postType): void;
}
