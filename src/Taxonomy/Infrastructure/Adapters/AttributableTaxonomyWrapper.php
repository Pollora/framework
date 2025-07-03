<?php

declare(strict_types=1);

namespace Pollora\Taxonomy\Infrastructure\Adapters;

use Pollora\Attributes\Contracts\Attributable;
use Pollora\Attributes\Taxonomy;
use Pollora\Taxonomy\Domain\Contracts\TaxonomyAttributeInterface;

/**
 * Wrapper class that makes any class with #[Taxonomy] attribute compatible with both
 * TaxonomyAttributeInterface and the new Attributable interface.
 *
 * This adapter allows classes marked with the #[Taxonomy] attribute to work with both
 * the existing attribute processing system and the new domain-based attribute system.
 * It bridges the gap between the new attribute-based approach and the existing infrastructure.
 */
final class AttributableTaxonomyWrapper implements Attributable, TaxonomyAttributeInterface
{
    /**
     * Arguments collected from attributes.
     *
     * @var array<string, mixed>
     */
    public array $attributeArgs = [];

    /**
     * Create a new wrapper instance.
     *
     * @param  object  $wrappedInstance  The original class instance
     * @param  Taxonomy  $taxonomyAttribute  The Taxonomy attribute instance
     * @param  string  $className  The fully qualified class name
     */
    public function __construct(
        private readonly object $wrappedInstance,
        private readonly Taxonomy $taxonomyAttribute,
        private readonly string $className
    ) {}

    /**
     * Get the taxonomy slug.
     *
     * Uses the Taxonomy attribute to determine the slug, with auto-generation fallback.
     *
     * @return string The taxonomy slug
     */
    public function getSlug(): string
    {
        return $this->taxonomyAttribute->getSlug($this->className);
    }

    /**
     * Get the singular name of the taxonomy.
     *
     * Uses the Taxonomy attribute to determine the singular name, with auto-generation fallback.
     *
     * @return string The singular name
     */
    public function getName(): string
    {
        return $this->taxonomyAttribute->getSingular($this->className);
    }

    /**
     * Get the plural name of the taxonomy.
     *
     * Uses the Taxonomy attribute to determine the plural name, with auto-generation fallback.
     *
     * @return string The plural name
     */
    public function getPluralName(): string
    {
        return $this->taxonomyAttribute->getPlural($this->className);
    }

    /**
     * Get the object types (post types) this taxonomy applies to.
     *
     * Uses the Taxonomy attribute to determine the object types.
     *
     * @return array<string>|string The object types
     */
    public function getObjectType(): array|string
    {
        return $this->attributeArgs['object_type'] ?? $this->taxonomyAttribute->getObjectType();
    }

    /**
     * Get additional arguments to merge with attribute-defined arguments.
     *
     * If the wrapped instance has a withArgs method, call it. Otherwise, return empty array.
     *
     * @return array<string, mixed> Additional arguments
     */
    public function withArgs(): array
    {
        if (method_exists($this->wrappedInstance, 'withArgs')) {
            return $this->wrappedInstance->withArgs();
        }

        return [];
    }

    /**
     * Get the wrapped instance.
     *
     * This allows access to the original instance for any additional processing needs.
     *
     * @return object The original wrapped instance
     */
    public function getWrappedInstance(): object
    {
        return $this->wrappedInstance;
    }

    /**
     * Get the labels for the taxonomy.
     *
     * Generates standard labels based on the singular and plural names.
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
     * Get the complete arguments for registering the taxonomy.
     *
     * Combines arguments from withArgs() method with those collected from attributes
     * and adds labels.
     *
     * @return array<string, mixed> Complete taxonomy arguments
     */
    public function getArgs(): array
    {
        return array_merge(
            $this->withArgs(),
            [
                'labels' => $this->getLabels(),
            ],
            $this->attributeArgs
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getSupportedDomains(): array
    {
        return ['taxonomy', 'hook'];
    }

    /**
     * {@inheritDoc}
     */
    public function supportsDomain(string $domain): bool
    {
        return in_array($domain, ['taxonomy', 'hook']);
    }
}
