<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Plugins\WooCommerce;

/**
 * Event fired when a WooCommerce tax rate is deleted.
 *
 * This event is triggered when a tax rate is removed from the system.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
class TaxRateDeleted extends WooCommerceEvent
{
    /**
     * Constructor.
     */
    public function __construct(
        public readonly int $taxRateId
    ) {
    }
} 