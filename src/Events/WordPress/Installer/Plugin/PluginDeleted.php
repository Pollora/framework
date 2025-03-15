<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Installer\Plugin;

/**
 * Event fired when a plugin is deleted.
 */
class PluginDeleted extends PluginEvent
{
    /**
     * Whether the plugin was deleted network wide.
     */
    public readonly bool $networkWide;
}
