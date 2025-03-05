<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Installer\Theme;

/**
 * Event fired when a theme is activated.
 *
 * This event is triggered when a theme is activated in WordPress,
 * becoming the active theme for the site.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
class ThemeActivated extends ThemeEvent
{
} 