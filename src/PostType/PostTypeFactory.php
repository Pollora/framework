<?php

declare(strict_types=1);

/**
 * Class PostTypeFactory
 *
 * This class is responsible for creating instances of the PostType class.
 */

namespace Pollen\PostType;

use Pollen\Foundation\Application;

class PostTypeFactory
{
    protected Application $container;

    public function __construct(Application $container)
    {
        $this->container = $container;
    }

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
    public function make(string $slug, ?string $singular, ?string $plural)
    {
        $abstract = sprintf('wp.posttype.%s', $slug);

        if ($this->container->bound($abstract)) {
            throw new PostTypeException(sprintf('The post type "%s" already exists.', $slug));
        }

        $postType = new PostType($slug, $singular, $plural);
        $this->container->instance($abstract, $postType);

        return $postType;
    }
}
