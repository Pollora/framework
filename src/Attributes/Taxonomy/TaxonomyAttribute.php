<?php

declare(strict_types=1);

namespace Pollora\Attributes\Taxonomy;

use Attribute;
use Pollora\Attributes\Attributable;
use Pollora\Attributes\HandlesAttributes;
use Pollora\Taxonomy\Contracts\Taxonomy;
use ReflectionClass;
use ReflectionMethod;

/**
 * Base attribute for taxonomy configuration.
 *
 * This attribute serves as the base for all taxonomy configuration attributes.
 * It provides common functionality for handling taxonomy registration arguments.
 *
 * @Attribute
 */
#[Attribute(Attribute::TARGET_CLASS)]
abstract class TaxonomyAttribute implements HandlesAttributes
{
    /**
     * Handle the attribute processing.
     *
     * This method is called by the AttributeProcessor when processing attributes
     * on a class. It validates that the class implements the Taxonomy interface
     * and then calls the configure method.
     *
     * @param  Attributable  $instance  The instance being processed
     * @param  ReflectionClass  $context  The reflection class of the instance
     * @param  self  $attribute  The attribute instance
     */
    public function handle(Attributable $instance, ReflectionClass|ReflectionMethod $context, object $attribute): void
    {
        if (! $instance instanceof Taxonomy) {
            return;
        }

        $this->configure($instance);
    }

    /**
     * Configure the taxonomy instance with this attribute.
     *
     * This method should be implemented by child classes to set specific
     * configuration options on the taxonomy instance.
     *
     * @param  Taxonomy  $taxonomy  The taxonomy instance to configure
     */
    abstract protected function configure(Taxonomy $taxonomy): void;
}
