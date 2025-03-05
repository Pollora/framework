<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Widget;

/**
 * Event fired when widgets within a sidebar are reordered.
 *
 * This event is triggered when the order of widgets in a sidebar is changed,
 * providing information about the sidebar where the reordering occurred.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
class WidgetReordered extends WidgetEvent
{
    /**
     * Constructor.
     *
     * @param string $sidebarId    The ID of the sidebar where widgets were reordered
     * @param array  $oldOrder     The previous order of widget IDs
     * @param array  $newOrder     The new order of widget IDs
     */
    public function __construct(
        string $sidebarId,
        public readonly array $oldOrder,
        public readonly array $newOrder
    ) {
        parent::__construct('', null, null, $sidebarId);
    }
} 