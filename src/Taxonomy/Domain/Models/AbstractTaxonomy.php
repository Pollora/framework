<?php

declare(strict_types=1);

namespace Pollora\Taxonomy\Domain\Models;

use Illuminate\Support\Str;
use Pollora\Support\Domain\StringHelper;
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

    public function setArg(string $key, mixed $value): void
    {
        $this->attributeArgs[$key] = $value;
    }

    public function getArg(string $key, mixed $default = null): mixed
    {
        return $this->attributeArgs[$key] ?? $default;
    }

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
            return StringHelper::kebab($className);
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
        $snakeCase = StringHelper::snake($className);

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

        return [
            'name' => $pluralName,
            'singular_name' => $name,
            'menu_name' => $pluralName,
            /* translators: %s: taxonomy general name (plural) */
            'all_items' => sprintf(__('All %s', 'pollora'), $pluralName),
            /* translators: %s: taxonomy singular name */
            'edit_item' => sprintf(__('Edit %s', 'pollora'), $name),
            /* translators: %s: taxonomy singular name */
            'view_item' => sprintf(__('View %s', 'pollora'), $name),
            /* translators: %s: taxonomy singular name */
            'update_item' => sprintf(__('Update %s', 'pollora'), $name),
            /* translators: %s: taxonomy singular name */
            'add_new_item' => sprintf(__('Add New %s', 'pollora'), $name),
            /* translators: %s: taxonomy singular name */
            'new_item_name' => sprintf(__('New %s Name', 'pollora'), $name),
            /* translators: %s: taxonomy general name (plural) */
            'search_items' => sprintf(__('Search %s', 'pollora'), $pluralName),
            /* translators: %s: taxonomy general name (plural) */
            'popular_items' => sprintf(__('Popular %s', 'pollora'), $pluralName),
            /* translators: %s: taxonomy general name (plural) */
            'separate_items_with_commas' => sprintf(__('Separate %s with commas', 'pollora'), $pluralName),
            /* translators: %s: taxonomy general name (plural) */
            'add_or_remove_items' => sprintf(__('Add or remove %s', 'pollora'), $pluralName),
            /* translators: %s: taxonomy general name (plural) */
            'choose_from_most_used' => sprintf(__('Choose from the most used %s', 'pollora'), $pluralName),
            /* translators: %s: taxonomy general name (plural) */
            'not_found' => sprintf(__('No %s found', 'pollora'), $pluralName),
            /* translators: %s: taxonomy singular name */
            'parent_item' => sprintf(__('Parent %s', 'pollora'), $name),
            /* translators: %s: taxonomy singular name */
            'parent_item_colon' => sprintf(__('Parent %s:', 'pollora'), $name),
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
