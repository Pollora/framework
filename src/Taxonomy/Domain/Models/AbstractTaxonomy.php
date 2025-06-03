<?php

declare(strict_types=1);

namespace Pollora\Taxonomy\Domain\Models;

use Illuminate\Support\Str;
use Pollora\Taxonomy\Domain\Contracts\TaxonomyAttributeInterface;

/**
 * Abstract base class for taxonomy definitions.
 *
 * This class provides a foundation for creating custom taxonomies using attributes.
 */
abstract class AbstractTaxonomy implements TaxonomyAttributeInterface
{
    /**
     * Arguments set by attributes.
     *
     * @var array<string, mixed>
     */
    public array $attributeArgs = [];

    /**
     * The taxonomy slug.
     */
    protected ?string $slug = null;

    /**
     * The post types this taxonomy is associated with.
     *
     * @var array<string>|string
     */
    protected array|string $objectType = [];

    /**
     * Get the slug for the taxonomy.
     *
     * If the slug is not explicitly set, it will be generated from the class name.
     */
    public function getSlug(): string
    {
        if ($this->slug === null) {
            // Get the class name without namespace
            $className = class_basename($this);

            // Convert to kebab-case
            return Str::kebab($className);
        }

        return $this->slug;
    }

    /**
     * Get the singular name of the taxonomy.
     *
     * This method can be overridden to provide a custom name.
     * By default, it generates a human-readable name from the class name.
     */
    public function getName(): string
    {
        // Get the class name without namespace
        $className = class_basename($this);

        // Convert to snake_case first
        $snakeCase = Str::snake($className);

        // Then humanize it (convert snake_case to words with spaces and capitalize first letter)
        $humanized = ucfirst(str_replace('_', ' ', $snakeCase));

        // Ensure it's singular
        return Str::singular($humanized);
    }

    /**
     * Get the plural name of the taxonomy.
     *
     * This method can be overridden to provide a custom plural name.
     * By default, it pluralizes the singular name.
     */
    public function getPluralName(): string
    {
        return Str::plural($this->getName());
    }

    /**
     * Get the post types this taxonomy is associated with.
     *
     * @return array<string>|string
     */
    public function getObjectType(): array|string
    {
        return $this->attributeArgs['object_type'] ?? $this->objectType;
    }

    /**
     * Additional arguments to merge with attribute-defined arguments.
     *
     * Override this method to add custom arguments that aren't covered by attributes.
     *
     * @return array<string, mixed>
     */
    public function withArgs(): array
    {
        return [];
    }

    /**
     * Get the labels for the taxonomy.
     *
     * This method can be overridden to provide custom labels.
     * By default, it generates standard labels based on the singular and plural names.
     *
     * @return array<string, string> The labels array
     */
    public function getLabels(): array
    {
        $name = $this->getName();
        $pluralName = $this->getPluralName();

        // Convert to lowercase for labels where the name is not in first position
        $lowerName = strtolower($name);
        $lowerPluralName = strtolower($pluralName);

        return [
            'name' => $pluralName,
            'singular_name' => $name,
            'menu_name' => $pluralName,
            'all_items' => __('All '.$lowerPluralName, 'textdomain'),
            'edit_item' => __('Edit '.$lowerName, 'textdomain'),
            'view_item' => __('View '.$lowerName, 'textdomain'),
            'update_item' => __('Update '.$lowerName, 'textdomain'),
            'add_new_item' => __('Add New '.$lowerName, 'textdomain'),
            'new_item_name' => __('New '.$lowerName.' Name', 'textdomain'),
            'search_items' => __('Search '.$lowerPluralName, 'textdomain'),
            'popular_items' => __('Popular '.$lowerPluralName, 'textdomain'),
            'separate_items_with_commas' => __('Separate '.$lowerPluralName.' with commas', 'textdomain'),
            'add_or_remove_items' => __('Add or remove '.$lowerPluralName, 'textdomain'),
            'choose_from_most_used' => __('Choose from the most used '.$lowerPluralName, 'textdomain'),
            'not_found' => __('No '.$lowerPluralName.' found', 'textdomain'),
            'parent_item' => __('Parent '.$lowerName, 'textdomain'),
            'parent_item_colon' => __('Parent '.$lowerName.':', 'textdomain'),
        ];
    }

    /**
     * Get the arguments for registering the taxonomy.
     *
     * @return array<string, mixed>
     */
    public function getArgs(): array
    {
        return array_merge(
            $this->attributeArgs,
            $this->withArgs(),
            [
                'labels' => $this->getLabels(),
            ]
        );
    }
}
