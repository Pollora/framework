<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Widget;

/**
 * Base class for all widget-related events.
 *
 * This abstract class provides the foundation for all widget events,
 * containing common properties shared across widget operations.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
abstract class WidgetEvent
{
    /**
     * Constructor.
     *
     * @param  string  $widgetId  The ID of the widget
     * @param  string|null  $widgetName  The name/type of the widget (e.g., "Archives", "Categories")
     * @param  string|null  $widgetTitle  The title given to the widget instance
     * @param  string|null  $sidebarId  The ID of the sidebar containing the widget
     */
    public function __construct(
        public readonly string $widgetId,
        public readonly ?string $widgetName = null,
        public readonly ?string $widgetTitle = null,
        public readonly ?string $sidebarId = null
    ) {}
}
