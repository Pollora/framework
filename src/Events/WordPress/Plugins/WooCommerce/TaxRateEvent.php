<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Plugins\WooCommerce;

/**
 * Base class for all WooCommerce tax rate-related events.
 *
 * This abstract class provides the foundation for all tax rate events,
 * containing the tax rate ID and data.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
abstract class TaxRateEvent extends WooCommerceEvent
{
    /**
     * Constructor.
     *
     * @param int $taxRateId The ID of the tax rate
     * @param array<string, mixed> $taxRate The tax rate data
     */
    public function __construct(
        public readonly int $taxRateId,
        public readonly array $taxRate
    ) {
    }
} 