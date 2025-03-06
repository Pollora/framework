<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Plugins\WooCommerce;

/**
 * Event fired when a WooCommerce product attribute is updated.
 *
 * This event is triggered when an existing product attribute is modified.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
class AttributeUpdated extends AttributeEvent {}
