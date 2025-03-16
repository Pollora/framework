<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Widget;

use Pollora\Events\WordPress\AbstractEventDispatcher;

/**
 * Event dispatcher for WordPress widget-related events.
 *
 * This class handles the dispatching of Laravel events for WordPress widget actions
 * such as adding, removing, moving, updating, and reordering widgets.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
class WidgetEventDispatcher extends AbstractEventDispatcher
{
    /**
     * WordPress actions to listen to.
     *
     * @var array<string>
     */
    protected array $actions = [
        'update_option_sidebars_widgets',
        'updated_option',
    ];

    /**
     * Handle changes to the sidebars_widgets option.
     *
     * @param  array  $oldWidgets  Previous widget configuration
     * @param  array  $newWidgets  New widget configuration
     */
    public function handleUpdateOptionSidebarsWidgets(array $oldWidgets, array $newWidgets): void
    {
        // Skip if we're switching themes
        if (did_action('after_switch_theme')) {
            return;
        }

        $this->handleWidgetChanges($oldWidgets, $newWidgets);
    }

    /**
     * Handle widget option updates.
     *
     * @param  string  $optionName  Name of the updated option
     * @param  mixed  $oldValue  Previous option value
     * @param  mixed  $newValue  New option value
     */
    public function handleUpdatedOption(string $optionName, mixed $oldValue, mixed $newValue): void
    {
        // Only process widget options
        if (! str_starts_with($optionName, 'widget_')) {
            return;
        }

        $widgetBase = substr($optionName, 7); // Remove 'widget_' prefix
        $this->handleWidgetInstanceUpdates($widgetBase, $oldValue, $newValue);
    }

    /**
     * Process changes in widget configurations.
     *
     * @param  array  $oldWidgets  Previous widget configuration
     * @param  array  $newWidgets  New widget configuration
     */
    protected function handleWidgetChanges(array $oldWidgets, array $newWidgets): void
    {
        foreach ($newWidgets as $sidebarId => $newSidebarWidgets) {
            if (! is_array($newSidebarWidgets)) {
                continue;
            }

            $oldSidebarWidgets = $oldWidgets[$sidebarId] ?? [];
            if (! is_array($oldSidebarWidgets)) {
                $oldSidebarWidgets = [];
            }

            // Handle widget reordering
            if (count($newSidebarWidgets) === count($oldSidebarWidgets) &&
                array_diff($newSidebarWidgets, $oldSidebarWidgets)) {
                $this->dispatch(WidgetReordered::class, [$sidebarId, $oldSidebarWidgets, $newSidebarWidgets]);

                continue;
            }

            // Handle added widgets
            $addedWidgets = array_diff($newSidebarWidgets, $oldSidebarWidgets);
            foreach ($addedWidgets as $widgetId) {
                $this->dispatch(WidgetAdded::class, [
                    $widgetId,
                    $this->getWidgetName($widgetId),
                    $this->getWidgetTitle($widgetId),
                    $sidebarId,
                ]);
            }

            // Handle removed widgets
            $removedWidgets = array_diff($oldSidebarWidgets, $newSidebarWidgets);
            foreach ($removedWidgets as $widgetId) {
                $this->dispatch(WidgetRemoved::class, [
                    $widgetId,
                    $this->getWidgetName($widgetId),
                    $this->getWidgetTitle($widgetId),
                    $sidebarId,
                ]);
            }
        }

        // Handle moved widgets
        $this->handleMovedWidgets($oldWidgets, $newWidgets);
    }

    /**
     * Process widget instance updates.
     *
     * @param  string  $widgetBase  Widget base ID
     * @param  mixed  $oldValue  Previous widget settings
     * @param  mixed  $newValue  New widget settings
     */
    protected function handleWidgetInstanceUpdates(string $widgetBase, mixed $oldValue, mixed $newValue): void
    {
        if (! is_array($oldValue) || ! is_array($newValue)) {
            return;
        }

        foreach ($newValue as $instanceId => $instance) {
            if (! isset($oldValue[$instanceId])) {
                continue;
            }

            $widgetId = $widgetBase.'-'.$instanceId;
            $this->dispatch(WidgetUpdated::class, [
                $widgetId,
                $oldValue[$instanceId],
                $instance,
                $this->getWidgetName($widgetId),
                $this->getWidgetTitle($widgetId),
                $this->getWidgetSidebarId($widgetId),
            ]);
        }
    }

    /**
     * Handle widgets that have been moved between sidebars.
     *
     * @param  array  $oldWidgets  Previous widget configuration
     * @param  array  $newWidgets  New widget configuration
     */
    protected function handleMovedWidgets(array $oldWidgets, array $newWidgets): void
    {
        $oldLocations = $this->getWidgetLocations($oldWidgets);
        $newLocations = $this->getWidgetLocations($newWidgets);

        foreach ($newLocations as $widgetId => $newSidebarId) {
            if (isset($oldLocations[$widgetId]) && $oldLocations[$widgetId] !== $newSidebarId) {
                $this->dispatch(WidgetMoved::class, [
                    $widgetId,
                    $oldLocations[$widgetId],
                    $newSidebarId,
                    $this->getWidgetName($widgetId),
                    $this->getWidgetTitle($widgetId),
                ]);
            }
        }
    }

    /**
     * Get a mapping of widget IDs to their sidebar locations.
     *
     * @param  array  $widgets  Widget configuration
     * @return array<string, string> Map of widget IDs to sidebar IDs
     */
    protected function getWidgetLocations(array $widgets): array
    {
        $locations = [];
        foreach ($widgets as $sidebarId => $sidebarWidgets) {
            if (! is_array($sidebarWidgets)) {
                continue;
            }
            foreach ($sidebarWidgets as $widgetId) {
                $locations[$widgetId] = $sidebarId;
            }
        }

        return $locations;
    }

    /**
     * Get the name/type of a widget.
     *
     * @param  string  $widgetId  Widget ID
     * @return string|null Widget name or null if not found
     */
    protected function getWidgetName(string $widgetId): ?string
    {
        global $wp_widget_factory;

        if (in_array(preg_match('/^(.+)-(\d+)$/', $widgetId, $matches), [0, false], true)) {
            return null;
        }

        $widgetBase = $matches[1];
        foreach ($wp_widget_factory->widgets as $widget) {
            if ($widget->id_base === $widgetBase) {
                return $widget->name;
            }
        }

        return null;
    }

    /**
     * Get the title of a widget instance.
     *
     * @param  string  $widgetId  Widget ID
     * @return string|null Widget title or null if not found
     */
    protected function getWidgetTitle(string $widgetId): ?string
    {
        if (in_array(preg_match('/^(.+)-(\d+)$/', $widgetId, $matches), [0, false], true)) {
            return null;
        }

        $widgetBase = $matches[1];
        $instanceId = (int) $matches[2];
        $instances = get_option('widget_'.$widgetBase);

        return $instances[$instanceId]['title'] ?? null;
    }

    /**
     * Get the sidebar ID containing a widget.
     *
     * @param  string  $widgetId  Widget ID
     * @return string|null Sidebar ID or null if not found
     */
    protected function getWidgetSidebarId(string $widgetId): ?string
    {
        $sidebars = wp_get_sidebars_widgets();
        foreach ($sidebars as $sidebarId => $widgets) {
            if (is_array($widgets) && in_array($widgetId, $widgets)) {
                return $sidebarId;
            }
        }

        return null;
    }
}
