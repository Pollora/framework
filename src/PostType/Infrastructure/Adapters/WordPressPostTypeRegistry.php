<?php

declare(strict_types=1);

namespace Pollora\PostType\Infrastructure\Adapters;

use Pollora\PostType\Domain\Contracts\PostTypeRegistryInterface;

/**
 * WordPress implementation of the PostTypeRegistry interface.
 */
class WordPressPostTypeRegistry implements PostTypeRegistryInterface
{
    /**
     * Register a post type with WordPress.
     *
     * @param  object  $postType  The post type to register
     * @return bool True if registration was successful
     */
    public function register(object $postType): bool
    {
        if (! function_exists('\register_post_type')) {
            return false;
        }

        $args = $this->preparePostTypeArgs($postType);
        $slug = method_exists($postType, 'getSlug') ? $postType->getSlug() : '';


        if (empty($slug)) {
            return false;
        }

        $result = \register_post_type($slug, $args);

        return ! \is_wp_error($result);
    }

    /**
     * Check if a post type exists in WordPress.
     *
     * @param  string  $slug  The post type slug to check
     * @return bool True if the post type exists
     */
    public function exists(string $slug): bool
    {
        if (function_exists('\post_type_exists')) {
            return \post_type_exists($slug);
        }

        return false;
    }

    /**
     * Get all registered post types from WordPress.
     *
     * @return array<string, mixed> The registered post types
     */
    public function getAll(): array
    {
        if (function_exists('\get_post_types')) {
            return \get_post_types(['_builtin' => false], 'objects');
        }

        return [];
    }

    /**
     * Prepare post type arguments for WordPress registration.
     *
     * @param  object  $postType  The post type object
     * @return array<string, mixed> The prepared arguments
     */
    private function preparePostTypeArgs(object $postType): array
    {
        // Use the methods if they exist
        $args = method_exists($postType, 'getArgs') ? $postType->getArgs() : [];

        // Add labels if not explicitly provided
        if (! isset($args['labels'])) {
            $singular = method_exists($postType, 'getSingularName') ? $postType->getSingularName() : '';
            $plural = method_exists($postType, 'getPluralName') ? $postType->getPluralName() : '';

            $args['labels'] = $this->generateLabels(
                $singular ?? '',
                $plural ?? ''
            );
        }

        return $args;
    }

    /**
     * Generate labels for the post type.
     *
     * @param  string  $singular  The singular name
     * @param  string  $plural  The plural name
     * @return array<string, string> The generated labels
     */
    private function generateLabels(string $singular, string $plural): array
    {
        // Convert to lowercase for labels where the name is not in first position
        $lowerSingular = strtolower($singular);
        $lowerPlural = strtolower($plural);

        return [
            'name' => $plural,
            'singular_name' => $singular,
            'menu_name' => $plural,
            'all_items' => __('All '.$lowerPlural, 'textdomain'),
            'add_new' => __('Add New', 'textdomain'),
            'add_new_item' => __('Add New '.$lowerSingular, 'textdomain'),
            'edit_item' => __('Edit '.$lowerSingular, 'textdomain'),
            'new_item' => __('New '.$lowerSingular, 'textdomain'),
            'view_item' => __('View '.$lowerSingular, 'textdomain'),
            'search_items' => __('Search '.$lowerPlural, 'textdomain'),
            'not_found' => __('No '.$lowerPlural.' found', 'textdomain'),
            'not_found_in_trash' => __('No '.$lowerPlural.' found in trash', 'textdomain'),
            'parent_item_colon' => __('Parent '.$lowerSingular.':', 'textdomain'),
        ];
    }
}
