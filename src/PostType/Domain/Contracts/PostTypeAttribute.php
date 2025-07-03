<?php

declare(strict_types=1);

namespace Pollora\PostType\Domain\Contracts;

use Attribute;
use Pollora\Attributes\Attributable;
use Pollora\Attributes\Contracts\HandlesAttributes;
use ReflectionClass;
use ReflectionMethod;

/**
 * Base attribute for post type configuration.
 *
 * This attribute serves as the base for all post type configuration attributes.
 * It provides common functionality for handling post type registration arguments.
 *
 * @Attribute
 */
#[Attribute(Attribute::TARGET_CLASS)]
abstract class PostTypeAttribute implements HandlesAttributes
{
    /**
     * Handle the attribute processing.
     *
     * This method is called by the AttributeProcessor when processing attributes
     * on a class. It validates that the class implements the PostType interface
     * and then calls the configure method.
     *
     * @param  Attributable  $instance  The instance being processed
     * @param  ReflectionClass|ReflectionMethod  $context  The reflection class of the instance
     * @param  object  $attribute  The attribute instance
     */
    public function handle($container, Attributable $instance, ReflectionClass|ReflectionMethod $context, object $attribute): void
    {
        if (! $instance instanceof PostTypeAttributeInterface) {
            return;
        }

        $this->configure($instance);
    }

    /**
     * Configure the post type instance with this attribute.
     *
     * This method should be implemented by child classes to set specific
     * configuration options on the post type instance.
     *
     * @param  PostTypeAttributeInterface  $postType  The post type instance to configure
     */
    abstract protected function configure(PostTypeAttributeInterface $postType): void;
}
