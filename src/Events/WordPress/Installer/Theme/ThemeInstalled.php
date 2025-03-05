<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Installer\Theme;

/**
 * Event fired when a theme is installed.
 *
 * This event is triggered when a new theme is successfully installed
 * in WordPress, either through the admin interface or programmatically.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
class ThemeInstalled extends ThemeEvent
{
} 