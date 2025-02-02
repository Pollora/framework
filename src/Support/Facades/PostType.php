<?php

declare(strict_types=1);

namespace Pollora\Support\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Facade for WordPress Post Type functionality.
 *
 * Provides a fluent interface for registering and managing WordPress custom post types
 * with improved type safety and validation.
 *
 * @method static \Pollora\PostType\PostType register(string $name, array $args = []) Register a new post type
 * @method static bool exists(string $postType) Check if a post type exists
 * @method static array getRegistered() Get all registered post types
 *
 * @see \Pollora\PostType\PostType
 */
class PostType extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'wp.posttype';
    }
}
