<?php

declare(strict_types=1);

namespace Pollora\Route\Infrastructure\Services\Resolvers;

use Pollora\Route\Infrastructure\Services\Contracts\WordPressTypeResolverInterface;

/**
 * Concrete implementation for resolving WordPress types.
 */
class WordPressTypeResolver implements WordPressTypeResolverInterface
{
    /**
     * @var array<string, callable>
     */
    private array $resolvers;

    /**
     * Create a new resolver instance and register default resolvers.
     */
    public function __construct()
    {
        $this->resolvers = [
            'WP_Post' => [$this, 'resolvePost'],
            'WP_Term' => [$this, 'resolveTerm'],
            'WP_User' => [$this, 'resolveUser'],
            'WP_Query' => [$this, 'resolveQuery'],
            'WP' => [$this, 'resolveWP'],
        ];
    }

    /**
     * Resolve a WordPress type by name.
     *
     * @param  string  $typeName  Fully qualified WP_* class name
     * @return mixed|null         The resolved object or null if unavailable
     */
    public function resolve(string $typeName): mixed
    {
        if (! isset($this->resolvers[$typeName])) {
            return null;
        }

        return call_user_func($this->resolvers[$typeName]);
    }

    /**
     * Resolve the current WP_Post object if available.
     *
     * @return \WP_Post|null
     */
    public function resolvePost(): ?\WP_Post
    {
        global $post;

        // First try global post
        if ($post instanceof \WP_Post) {
            return $post;
        }

        // Try queried object if it's a post
        if (function_exists('get_queried_object')) {
            $queried = get_queried_object();
            if ($queried instanceof \WP_Post) {
                return $queried;
            }
        }

        return null;
    }

    /**
     * Resolve the current WP_Term object if available.
     *
     * @return \WP_Term|null
     */
    public function resolveTerm(): ?\WP_Term
    {
        if (! function_exists('get_queried_object')) {
            return null;
        }

        $queried = get_queried_object();

        return $queried instanceof \WP_Term ? $queried : null;
    }

    /**
     * Resolve the current WP_User object if available.
     *
     * @return \WP_User|null
     */
    public function resolveUser(): ?\WP_User
    {
        if (function_exists('get_queried_object')) {
            $queried = get_queried_object();
            if ($queried instanceof \WP_User) {
                return $queried;
            }
        }

        // Fallback to current user
        if (function_exists('wp_get_current_user')) {
            $current_user = wp_get_current_user();
            if ($current_user instanceof \WP_User && $current_user->ID > 0) {
                return $current_user;
            }
        }

        return null;
    }

    /**
     * Resolve the global WP_Query instance.
     *
     * @return \WP_Query|null
     */
    public function resolveQuery(): ?\WP_Query
    {
        global $wp_query;

        return $wp_query instanceof \WP_Query ? $wp_query : null;
    }

    /**
     * Resolve the global WP instance.
     *
     * @return \WP|null
     */
    public function resolveWP(): ?\WP
    {
        global $wp;

        return $wp instanceof \WP ? $wp : null;
    }
}
