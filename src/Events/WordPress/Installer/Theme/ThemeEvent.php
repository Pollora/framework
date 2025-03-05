<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Installer\Theme;

use Pollora\Events\WordPress\Installer\InstallerEvent;

/**
 * Base class for all theme-related events.
 *
 * This abstract class extends the InstallerEvent class and provides
 * the foundation for all theme-specific events.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
abstract class ThemeEvent extends InstallerEvent
{
} 