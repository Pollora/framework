<?php

declare(strict_types=1);

namespace Pollora\PostType\Infrastructure\Adapters;

use Pollora\Attributes\Contracts\Attributable;
use Pollora\Attributes\PostType;
use Pollora\PostType\Domain\Contracts\PostTypeAttributeInterface;

/**
 * Wrapper class that makes any class with #[PostType] attribute compatible with both
 * PostTypeAttributeInterface and the new Attributable interface.
 *
 * This adapter allows classes marked with the #[PostType] attribute to work with both
 * the existing attribute processing system and the new domain-based attribute system.
 * It bridges the gap between the new attribute-based approach and the existing infrastructure.
 */
final class AttributablePostTypeWrapper implements Attributable, PostTypeAttributeInterface
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
     * @param  PostType  $postTypeAttribute  The PostType attribute instance
     * @param  string  $className  The fully qualified class name
     */
    public function __construct(
        private readonly object $wrappedInstance,
        private readonly PostType $postTypeAttribute,
        private readonly string $className
    ) {}

    /**
     * Get the post type slug.
     *
     * Uses the PostType attribute to determine the slug, with auto-generation fallback.
     *
     * @return string The post type slug
     */
    public function getSlug(): string
    {
        return $this->postTypeAttribute->getSlug($this->className);
    }

    /**
     * Get the singular name of the post type.
     *
     * Uses the PostType attribute to determine the singular name, with auto-generation fallback.
     *
     * @return string The singular name
     */
    public function getName(): string
    {
        return $this->postTypeAttribute->getSingular($this->className);
    }

    /**
     * Get the plural name of the post type.
     *
     * Uses the PostType attribute to determine the plural name, with auto-generation fallback.
     *
     * @return string The plural name
     */
    public function getPluralName(): string
    {
        return $this->postTypeAttribute->getPlural($this->className);
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
     * Get the complete arguments for registering the post type.
     *
     * Combines arguments from withArgs() method with those collected from attributes
     * and adds labels.
     *
     * @return array<string, mixed> Complete post type arguments
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
     * Get the labels for the post type.
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
     * {@inheritDoc}
     */
    public function getSupportedDomains(): array
    {
        return ['post_type', 'hook'];
    }

    /**
     * {@inheritDoc}
     */
    public function supportsDomain(string $domain): bool
    {
        return in_array($domain, ['post_type', 'hook']);
    }
}
