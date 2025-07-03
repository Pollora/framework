<?php

declare(strict_types=1);

namespace Pollora\Taxonomy\Domain\Contracts;

/**
 * Interface for Taxonomy application services.
 *
 * This interface defines the contract for managing taxonomies across the application.
 * It provides methods for creating, registering, and querying taxonomies while
 * maintaining separation of concerns between different implementations.
 */
interface TaxonomyServiceInterface
{
    /**
     * Create a new post type instance.
     *
     * @param  string  $slug  The post type slug
     * @param  string|array  $objectType  The post type(s) to be associated
     * @param  string|null  $singular  The singular label for the post type
     * @param  string|null  $plural  The plural label for the post type
     * @param  array<string, mixed>  $args  Additional arguments
     * @return object The created post type instance
     */
    public function create(string $slug, string|array $objectType, ?string $singular = null, ?string $plural = null, array $args = []): object;

    /**
     * Register a post type with the system.
     *
     * @param  string  $slug  The post type slug
     * @param  string|null  $singular  The singular label for the post type
     * @param  string|null  $plural  The plural label for the post type
     * @param  array<string, mixed>  $args  Additional arguments
     * @return object The registered post type instance
     */
    public function register(string $slug, string|array $objectType, ?string $singular = null, ?string $plural = null, array $args = []): object;

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
     * Register a taxonomy from a class with Taxonomy attribute.
     *
     * @param string $className The fully qualified class name
     * @return object|null The registered taxonomy instance or null if failed
     */
    public function registerFromClass(string $className): ?object;
}
