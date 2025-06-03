<?php

declare(strict_types=1);

namespace Pollora\Route\Infrastructure\Services\Contracts;

/**
 * Interface for resolving WordPress types.
 */
interface WordPressTypeResolverInterface
{
    /**
     * Resolve a WordPress type by its class name.
     */
    public function resolve(string $typeName): mixed;

    /**
     * Resolve WP_Post object.
     */
    public function resolvePost(): ?\WP_Post;

    /**
     * Resolve WP_Term object.
     */
    public function resolveTerm(): ?\WP_Term;

    /**
     * Resolve WP_User object.
     */
    public function resolveUser(): ?\WP_User;

    /**
     * Resolve WP_Query object.
     */
    public function resolveQuery(): ?\WP_Query;

    /**
     * Resolve WP object.
     */
    public function resolveWP(): ?\WP;
}
