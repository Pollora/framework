<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Installer\Plugin;

use Pollora\Events\WordPress\Installer\InstallerEvent;

/**
 * Base class for all plugin-related events.
 *
 * This abstract class extends the InstallerEvent class and adds
 * plugin-specific properties like network-wide activation.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
abstract class PluginEvent extends InstallerEvent
{
    /**
     * Constructor.
     *
     * @param  string  $name  Plugin name
     * @param  string|null  $version  Plugin version
     * @param  string|null  $slug  Plugin slug
     * @param  bool  $networkWide  Whether the plugin is network activated
     */
    public function __construct(
        string $name,
        ?string $version = null,
        ?string $slug = null,
        public readonly bool $networkWide = false
    ) {
        parent::__construct($name, $version, $slug);
    }
}
