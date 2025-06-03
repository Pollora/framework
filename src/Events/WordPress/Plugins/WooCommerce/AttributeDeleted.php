<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Plugins\WooCommerce;

/**
 * Event fired when a WooCommerce product attribute is deleted.
 *
 * This event is triggered when a product attribute is removed from the system.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
class AttributeDeleted extends WooCommerceEvent
{
    /**
     * Constructor.
     */
    public function __construct(
        public readonly int $attributeId,
        public readonly string $attributeName
    ) {}
}
