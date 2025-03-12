<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Installer\Plugin;

/**
 * Event fired when a plugin is installed.
 *
 * This event is triggered when a new plugin is successfully installed
 * in WordPress, either through the admin interface or programmatically.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
class PluginInstalled extends PluginEvent {}
