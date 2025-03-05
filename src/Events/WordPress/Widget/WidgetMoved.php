<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Widget;

/**
 * Event fired when a widget is moved from one sidebar to another.
 *
 * This event is triggered when a widget is moved between different sidebars,
 * providing both the source and destination sidebar information.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
class WidgetMoved extends WidgetEvent
{
    /**
     * Constructor.
     *
     * @param string      $widgetId        The ID of the widget
     * @param string      $oldSidebarId    The ID of the source sidebar
     * @param string      $newSidebarId    The ID of the destination sidebar
     * @param string|null $widgetName      The name/type of the widget
     * @param string|null $widgetTitle     The title given to the widget instance
     */
    public function __construct(
        string $widgetId,
        public readonly string $oldSidebarId,
        public readonly string $newSidebarId,
        ?string $widgetName = null,
        ?string $widgetTitle = null
    ) {
        parent::__construct($widgetId, $widgetName, $widgetTitle, $newSidebarId);
    }
} 