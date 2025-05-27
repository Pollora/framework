<?php

declare(strict_types=1);

namespace Pollora\Route\Domain\Services;

/**
 * WordPress Context Builder Service
 *
 * Centralizes the building of WordPress context for template resolution.
 * Handles different WordPress objects (post, term, user) and their context.
 */
class WordPressContextBuilder
{
    /**
     * Build enhanced context with WordPress information
     */
    public function buildContext(array $baseContext = []): array
    {
        global $wp_query, $post, $wp;

        $context = $baseContext;

        // Add WordPress globals if not present
        if (! isset($context['wp_query']) && isset($wp_query)) {
            $context['wp_query'] = $wp_query;
        }

        if (! isset($context['post']) && isset($post)) {
            $context['post'] = $post;
        }

        if (! isset($context['wp']) && isset($wp)) {
            $context['wp'] = $wp;
        }

        // Add queried object context
        $context = $this->addQueriedObjectContext($context);

        // Add common WordPress context
        $context = $this->addCommonWordPressContext($context);

        // Add theme information
        $context = $this->addThemeContext($context);

        return $context;
    }

    /**
     * Build context for a specific post
     */
    public function buildPostContext(int $postId, array $baseContext = []): array
    {
        if (! function_exists('get_post')) {
            return $baseContext;
        }

        $post = get_post($postId);
        if (! $post) {
            return $baseContext;
        }

        return array_merge($baseContext, [
            'post' => $post,
            'post_type' => $post->post_type,
            'post_id' => $post->ID,
            'post_name' => $post->post_name,
            'post_status' => $post->post_status,
        ]);
    }

    /**
     * Build context for a specific taxonomy term
     */
    public function buildTermContext(int $termId, string $taxonomy, array $baseContext = []): array
    {
        if (! function_exists('get_term')) {
            return $baseContext;
        }

        $term = get_term($termId, $taxonomy);
        if (! $term || is_wp_error($term)) {
            return $baseContext;
        }

        return array_merge($baseContext, [
            'term' => $term,
            'taxonomy' => $taxonomy,
            'term_id' => $term->term_id,
            'term_slug' => $term->slug ?? '',
            'term_name' => $term->name ?? '',
        ]);
    }

    /**
     * Build context for a specific user/author
     */
    public function buildUserContext(int $userId, array $baseContext = []): array
    {
        if (! function_exists('get_userdata')) {
            return $baseContext;
        }

        $user = get_userdata($userId);
        if (! $user) {
            return $baseContext;
        }

        return array_merge($baseContext, [
            'user' => $user,
            'author' => $user, // Alias for template consistency
            'user_id' => $user->ID,
            'user_nicename' => $user->user_nicename,
            'user_login' => $user->user_login,
        ]);
    }

    /**
     * Build context for archive pages
     */
    public function buildArchiveContext(string $postType = '', array $baseContext = []): array
    {
        return array_merge($baseContext, [
            'is_archive' => true,
            'archive_post_type' => $postType,
        ]);
    }

    /**
     * Extract post context from the current context
     */
    public function extractPostFromContext(array $context): ?object
    {
        if (isset($context['post'])) {
            return $context['post'];
        }

        // Fallback to WordPress global if no context post
        if (function_exists('get_post')) {
            return get_post();
        }

        return null;
    }

    /**
     * Extract term context from the current context
     */
    public function extractTermFromContext(array $context): ?object
    {
        if (isset($context['term'])) {
            return $context['term'];
        }

        // Fallback to queried object if it's a term
        if (function_exists('get_queried_object')) {
            $obj = get_queried_object();
            if ($obj && is_a($obj, 'WP_Term')) {
                return $obj;
            }
        }

        return null;
    }

    /**
     * Extract user/author context from the current context
     */
    public function extractUserFromContext(array $context): ?object
    {
        if (isset($context['user'])) {
            return $context['user'];
        }

        if (isset($context['author'])) {
            return $context['author'];
        }

        // Fallback to queried object if it's a user
        if (function_exists('get_queried_object')) {
            $obj = get_queried_object();
            if ($obj && is_a($obj, 'WP_User')) {
                return $obj;
            }
        }

        return null;
    }

    /**
     * Add queried object context
     */
    private function addQueriedObjectContext(array $context): array
    {
        if (! function_exists('get_queried_object')) {
            return $context;
        }

        $queriedObject = get_queried_object();
        if (! $queriedObject) {
            return $context;
        }

        $context['queried_object'] = $queriedObject;

        // Add specific context based on object type
        if (is_a($queriedObject, 'WP_Post')) {
            $context['post'] = $queriedObject;
            $context['post_type'] = $queriedObject->post_type;
            $context['post_id'] = $queriedObject->ID;
        } elseif (is_a($queriedObject, 'WP_Term')) {
            $context['term'] = $queriedObject;
            $context['taxonomy'] = $queriedObject->taxonomy;
            $context['term_id'] = $queriedObject->term_id;
        } elseif (is_a($queriedObject, 'WP_User')) {
            $context['user'] = $queriedObject;
            $context['author'] = $queriedObject;
            $context['user_id'] = $queriedObject->ID;
        }

        return $context;
    }

    /**
     * Add common WordPress context
     */
    private function addCommonWordPressContext(array $context): array
    {
        if (function_exists('is_admin')) {
            $context['is_admin'] = is_admin();
        }

        if (function_exists('is_main_query') && isset($context['wp_query'])) {
            // Check if wp_query has the is_main_query method (real WP_Query object)
            if (method_exists($context['wp_query'], 'is_main_query')) {
                $context['is_main_query'] = $context['wp_query']->is_main_query();
            } else {
                // Fallback for tests or when wp_query is not a proper WP_Query object
                $context['is_main_query'] = is_main_query();
            }
        }

        return $context;
    }

    /**
     * Add theme context
     */
    private function addThemeContext(array $context): array
    {
        if (function_exists('get_template')) {
            $context['active_theme'] = get_template();
        }

        if (function_exists('get_stylesheet')) {
            $context['active_stylesheet'] = get_stylesheet();
        }

        return $context;
    }
}
