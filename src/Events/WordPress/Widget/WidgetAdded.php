<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Widget;

/**
 * Event fired when a widget is added to a sidebar.
 *
 * This event is triggered when a new widget is added to any sidebar,
 * including when widgets are moved from the inactive widgets area.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
class WidgetAdded extends WidgetEvent
{
} 