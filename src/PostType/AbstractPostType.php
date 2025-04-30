<?php

declare(strict_types=1);

namespace Pollora\PostType;

use Illuminate\Support\Str;
use Pollora\Attributes\Attributable;
use Pollora\Discoverer\Contracts\Discoverable;
use Pollora\PostType\Contracts\PostType;

/**
 * Abstract base class for custom post types.
 *
 * Provides a foundation for creating custom post types with PHP attributes.
 * Implements the PostType interface and provides default implementations
 * for common methods.
 */
abstract class AbstractPostType implements Attributable, PostType
{
    /**
     * The post type slug.
     */
    protected ?string $slug = null;

    /**
     * Arguments collected from attributes.
     *
     * @var array<string, mixed>
     */
    public array $attributeArgs = [];

    /**
     * Get the post type slug.
     *
     * If the slug is not explicitly set, it will be generated from the class name.
     *
     * @return string The post type slug
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
     * Get the singular name of the post type.
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
     * Get the plural name of the post type.
     *
     * This method can be overridden to provide a custom plural name.
     * By default, it pluralizes the singular name.
     */
    public function getPluralName(): string
    {
        return Str::plural($this->getName());
    }

    /**
     * Get additional arguments to merge with attribute-defined arguments.
     *
     * Override this method to provide additional arguments that will be merged
     * with those defined via attributes.
     *
     * @return array<string, mixed> Additional arguments
     */
    public function withArgs(): array
    {
        return [];
    }

    /**
     * Get the labels for the post type.
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
            'add_new' => __('Add New', 'textdomain'),
            'add_new_item' => __('Add New '.$lowerName, 'textdomain'),
            'edit_item' => __('Edit '.$lowerName, 'textdomain'),
            'new_item' => __('New '.$lowerName, 'textdomain'),
            'view_item' => __('View '.$lowerName, 'textdomain'),
            'search_items' => __('Search '.$lowerPluralName, 'textdomain'),
            'not_found' => __('No '.$lowerPluralName.' found', 'textdomain'),
            'not_found_in_trash' => __('No '.$lowerPluralName.' found in trash', 'textdomain'),
            'parent_item_colon' => __('Parent '.$lowerName.':', 'textdomain'),
        ];
    }

    /**
     * Get the complete arguments for registering the post type.
     *
     * Combines arguments from attributes with those from withArgs() method
     * and adds labels.
     *
     * @return array<string, mixed> Complete post type arguments
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
