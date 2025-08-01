<?php

declare(strict_types=1);

namespace Pollora\Attributes\Taxonomy;

use Attribute;
use Pollora\Attributes\Contracts\HandlesAttributes;
use Pollora\Taxonomy\Domain\Contracts\TaxonomyAttributeInterface;
use ReflectionClass;
use ReflectionMethod;

/**
 * Attribute to define a callback function that will be called when the count is updated.
 *
 * This attribute can be applied to methods to define the callback
 * function that will be called when the count is updated.
 *
 * @Attribute
 */
#[Attribute(Attribute::TARGET_METHOD)]
class UpdateCountCallback implements HandlesAttributes
{
    /**
     * Handle the attribute processing.
     *
     * @param  object  $instance  The instance being processed
     * @param  ReflectionMethod|ReflectionClass  $context  The method the attribute is applied to
     * @param  object  $attribute  The attribute instance
     */
    public function handle($container, object $instance, ReflectionMethod|ReflectionClass $context, object $attribute): void
    {
        if (! $instance instanceof TaxonomyAttributeInterface) {
            return;
        }

        // Set the update_count_callback parameter to the method
        $instance->attributeArgs['update_count_callback'] = [new $context->class, $context->getName()];
    }
}
