<?php

declare(strict_types=1);

/**
 * Factory for creating and configuring custom post types.
 *
 * This class provides methods for creating PostType instances
 * and handles the interaction with WordPress registration.
 */

namespace Pollora\PostType;

use Pollora\Entity\PostType;
use Pollora\PostType\Domain\Contracts\PostTypeFactoryInterface;
use Psr\Container\ContainerInterface;

class PostTypeFactory implements PostTypeFactoryInterface
{
    /**
     * The application instance.
     */
    protected ContainerInterface $app;

    /**
     * Create a new PostTypeFactory instance.
     */
    public function __construct(ContainerInterface $app)
    {
        $this->app = $app;
    }

    /**
     * Create a new post type instance.
     *
     * @param string $slug The post type slug
     * @param string|null $singular The singular label for the post type
     * @param string|null $plural The plural label for the post type
     * @return PostType
     */
    public function make(string $slug, ?string $singular = null, ?string $plural = null): PostType
    {
        return new PostType($slug, $singular, $plural);
    }

    /**
     * Check if a post type exists.
     *
     * @param string $postType The post type slug to check
     * @return bool
     */
    public function exists(string $postType): bool
    {
        // Implementation would depend on WordPress functions or your own abstraction
        // This is a placeholder for the actual implementation
        // In a WordPress context, this might use post_type_exists()
        return false;
    }

    /**
     * Get all registered post types.
     *
     * @return array
     */
    public function getRegistered(): array
    {
        // Implementation would depend on WordPress functions or your own abstraction
        // This is a placeholder for the actual implementation
        // In a WordPress context, this might use get_post_types()
        return [];
    }
}
