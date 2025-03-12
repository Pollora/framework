<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Widget;

/**
 * Event fired when a widget is removed from a sidebar.
 *
 * This event is triggered when a widget is removed from any sidebar,
 * including when widgets are moved to the inactive widgets area.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
class WidgetRemoved extends WidgetEvent {}
