<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Widget;

/**
 * Event fired when a widget's settings are updated.
 *
 * This event is triggered when a widget's configuration is modified,
 * such as changing its title or other widget-specific settings.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
class WidgetUpdated extends WidgetEvent
{
    /**
     * Constructor.
     *
     * @param  string  $widgetId  The ID of the widget
     * @param  mixed  $oldInstance  The previous widget settings
     * @param  mixed  $newInstance  The updated widget settings
     * @param  string|null  $widgetName  The name/type of the widget
     * @param  string|null  $widgetTitle  The title given to the widget instance
     * @param  string|null  $sidebarId  The ID of the sidebar containing the widget
     */
    public function __construct(
        string $widgetId,
        public readonly mixed $oldInstance,
        public readonly mixed $newInstance,
        ?string $widgetName = null,
        ?string $widgetTitle = null,
        ?string $sidebarId = null
    ) {
        parent::__construct($widgetId, $widgetName, $widgetTitle, $sidebarId);
    }
}
