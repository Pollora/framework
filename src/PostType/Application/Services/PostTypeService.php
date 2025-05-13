<?php

declare(strict_types=1);

namespace Pollora\PostType\Application\Services;

use Pollora\Entity\PostType;
use Pollora\PostType\Domain\Contracts\PostTypeFactoryInterface;

/**
 * Service for managing post types in the application layer.
 * 
 * This service provides methods for working with post types,
 * following hexagonal architecture by using the domain contracts.
 */
class PostTypeService
{
    /**
     * The post type factory implementation.
     */
    private PostTypeFactoryInterface $factory;

    /**
     * Create a new PostTypeService instance.
     */
    public function __construct(PostTypeFactoryInterface $factory)
    {
        $this->factory = $factory;
    }

    /**
     * Create a new post type instance.
     * 
     * @param string $slug The post type slug
     * @param string|null $singular The singular label for the post type
     * @param string|null $plural The plural label for the post type
     * @return PostType
     */
    public function register(string $slug, ?string $singular = null, ?string $plural = null): PostType
    {
        return $this->factory->make($slug, $singular, $plural);
    }

    /**
     * Check if a post type exists.
     * 
     * @param string $postType The post type slug to check
     * @return bool
     */
    public function exists(string $postType): bool
    {
        return $this->factory->exists($postType);
    }

    /**
     * Get all registered post types.
     * 
     * @return array
     */
    public function getRegistered(): array
    {
        return $this->factory->getRegistered();
    }
} 