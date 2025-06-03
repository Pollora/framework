<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Installer\Plugin;

/**
 * Event fired when a plugin is updated.
 *
 * This event is triggered when a plugin is updated to a new version
 * in WordPress, either through the admin interface or programmatically.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
class PluginUpdated extends PluginEvent
{
    /**
     * Constructor.
     *
     * @param  string  $name  Plugin name
     * @param  string|null  $version  New plugin version
     * @param  string|null  $slug  Plugin slug
     * @param  bool  $networkWide  Whether the plugin is network activated
     * @param  string|null  $oldVersion  Previous plugin version
     */
    public function __construct(
        string $name,
        ?string $version = null,
        ?string $slug = null,
        bool $networkWide = false,
        public readonly ?string $oldVersion = null
    ) {
        parent::__construct($name, $version, $slug, $networkWide);
    }
}
