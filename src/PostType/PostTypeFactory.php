<?php

declare(strict_types=1);

/**
 * Class PostTypeFactory
 *
 * This class is responsible for creating instances of the PostType class.
 */

namespace Pollora\PostType;

use Illuminate\Foundation\Application;
use Pollora\Entity\PostTypeException;

class PostTypeFactory
{
    public function __construct(protected Application $container) {}

    /**
     * Creates a new post type and registers it in the container.
     *
     * @param  string  $slug  The slug for the post type.
     * @param  string|null  $singular  The singular name for the post type. Optional.
     * @param  string|null  $plural  The plural name for the post type. Optional.
     * @return PostType The created post type object.
     *
     * @throws PostTypeException if the post type with the given slug already exists.
     */
    public function make(string $slug, ?string $singular, ?string $plural): \Pollora\PostType\PostType
    {
        $abstract = sprintf('wp.posttype.%s', $slug);

        if ($this->container->bound($abstract)) {
            throw new PostTypeException(sprintf('The post type "%s" already exists.', $slug));
        }

        $postType = new PostType($slug, $singular, $plural);
        $postType->init();

        // Bind the instance to the container
        $this->container->instance($abstract, $postType);

        // Register the post type for WordPress
        if (function_exists('add_action')) {
            add_action('init', function () use ($postType) {
                $postType->registerEntityType();
            }, 99);
        }

        return $postType;
    }
}
