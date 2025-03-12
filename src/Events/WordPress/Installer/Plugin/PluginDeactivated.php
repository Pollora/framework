<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Installer\Plugin;

/**
 * Event fired when a plugin is deactivated.
 *
 * This event is triggered when a plugin is deactivated in WordPress,
 * either on a single site or network-wide in a multisite setup.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
class PluginDeactivated extends PluginEvent {}
