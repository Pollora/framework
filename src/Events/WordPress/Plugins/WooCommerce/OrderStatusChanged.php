<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Plugins\WooCommerce;

use WC_Order;

/**
 * Event fired when a WooCommerce order status is changed.
 *
 * This event is triggered when an order's status is modified, providing
 * the order object and both the old and new status values.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
class OrderStatusChanged extends WooCommerceEvent
{
    /**
     * Constructor.
     */
    public function __construct(
        public readonly WC_Order $order,
        public readonly string $oldStatus,
        public readonly string $newStatus
    ) {}
}
