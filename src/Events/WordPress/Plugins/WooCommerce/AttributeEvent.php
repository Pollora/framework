<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Plugins\WooCommerce;

/**
 * Base class for all WooCommerce attribute-related events.
 *
 * This abstract class provides the foundation for all attribute events,
 * containing the attribute ID and data.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
abstract class AttributeEvent extends WooCommerceEvent
{
    /**
     * Constructor.
     *
     * @param int $attributeId The ID of the attribute
     * @param array<string, mixed> $attribute The attribute data
     */
    public function __construct(
        public readonly int $attributeId,
        public readonly array $attribute
    ) {
    }
} 