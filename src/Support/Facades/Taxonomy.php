<?php

declare(strict_types=1);

namespace Pollora\Support\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Facade for WordPress Taxonomy functionality.
 *
 * Provides a fluent interface for registering and managing WordPress taxonomies
 * with improved type safety and validation.
 *
 * @method static \Pollora\Taxonomy\Taxonomy register(string $name, string|array $postTypes, array $args = []) Register a new taxonomy
 * @method static bool exists(string $taxonomy) Check if a taxonomy exists
 * @method static array getRegistered() Get all registered taxonomies
 *
 * @see \Pollora\Taxonomy\Taxonomy
 */
class Taxonomy extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'wp.taxonomy';
    }
}
