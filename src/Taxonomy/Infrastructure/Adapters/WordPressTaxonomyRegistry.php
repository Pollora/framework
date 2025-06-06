<?php

declare(strict_types=1);

namespace Pollora\Taxonomy\Infrastructure\Adapters;

use Pollora\Taxonomy\Domain\Contracts\TaxonomyRegistryInterface;

/**
 * WordPress implementation of the TaxonomyRegistry interface.
 */
class WordPressTaxonomyRegistry implements TaxonomyRegistryInterface
{
    /**
     * Register a taxonomy with WordPress.
     *
     * @param  object  $taxonomy  The taxonomy to register
     * @return bool True if registration was successful
     */
    public function register(object $taxonomy): bool
    {
        if (! function_exists('\register_taxonomy')) {
            return false;
        }

        $args = $this->prepareTaxonomyArgs($taxonomy);
        $slug = method_exists($taxonomy, 'getSlug') ? $taxonomy->getSlug() : '';
        $objectType = method_exists($taxonomy, 'getObjectType') ? $taxonomy->getObjectType() : 'post';

        if (empty($slug)) {
            return false;
        }

        $result = \register_taxonomy($slug, $objectType, $args);

        return ! \is_wp_error($result);
    }

    /**
     * Check if a taxonomy exists in WordPress.
     *
     * @param  string  $slug  The taxonomy slug to check
     * @return bool True if the taxonomy exists
     */
    public function exists(string $slug): bool
    {
        if (function_exists('\taxonomy_exists')) {
            return \taxonomy_exists($slug);
        }

        return false;
    }

    /**
     * Get all registered taxonomies from WordPress.
     *
     * @return array<string, mixed> The registered taxonomies
     */
    public function getAll(): array
    {
        if (function_exists('\get_taxonomies')) {
            return \get_taxonomies(['_builtin' => false], 'objects');
        }

        return [];
    }

    /**
     * Prepare taxonomy arguments for WordPress registration.
     *
     * @param  object  $taxonomy  The taxonomy object
     * @return array<string, mixed> The prepared arguments
     */
    private function prepareTaxonomyArgs(object $taxonomy): array
    {
        // Use the methods if they exist
        $args = method_exists($taxonomy, 'getArgs') ? $taxonomy->getArgs() : [];

        // Add labels if not explicitly provided
        if (! isset($args['labels'])) {
            $singular = method_exists($taxonomy, 'getSingularName') ? $taxonomy->getSingularName() : '';
            $plural = method_exists($taxonomy, 'getPluralName') ? $taxonomy->getPluralName() : '';

            $args['labels'] = $this->generateLabels(
                $singular ?? '',
                $plural ?? ''
            );
        }

        return $args;
    }

    /**
     * Generate labels for the taxonomy.
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
            'edit_item' => __('Edit '.$lowerSingular, 'textdomain'),
            'view_item' => __('View '.$lowerSingular, 'textdomain'),
            'update_item' => __('Update '.$lowerSingular, 'textdomain'),
            'add_new_item' => __('Add New '.$lowerSingular, 'textdomain'),
            'new_item_name' => __('New '.$lowerSingular.' Name', 'textdomain'),
            'search_items' => __('Search '.$lowerPlural, 'textdomain'),
            'popular_items' => __('Popular '.$lowerPlural, 'textdomain'),
            'separate_items_with_commas' => __('Separate '.$lowerPlural.' with commas', 'textdomain'),
            'add_or_remove_items' => __('Add or remove '.$lowerPlural, 'textdomain'),
            'choose_from_most_used' => __('Choose from the most used '.$lowerPlural, 'textdomain'),
            'not_found' => __('No '.$lowerPlural.' found', 'textdomain'),
            'parent_item' => __('Parent '.$lowerSingular, 'textdomain'),
            'parent_item_colon' => __('Parent '.$lowerSingular.':', 'textdomain'),
        ];
    }
}
