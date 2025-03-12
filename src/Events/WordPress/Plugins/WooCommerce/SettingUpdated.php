<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Plugins\WooCommerce;

/**
 * Event fired when a WooCommerce setting is updated.
 *
 * This event is triggered when any WooCommerce option is modified.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
class SettingUpdated extends WooCommerceEvent
{
    /**
     * Constructor.
     *
     * @param  string  $optionName  The name of the option
     * @param  mixed  $oldValue  The previous value
     * @param  mixed  $newValue  The new value
     */
    public function __construct(
        public readonly string $optionName,
        public readonly mixed $oldValue,
        public readonly mixed $newValue
    ) {}
}
