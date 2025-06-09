<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Installer;

/**
 * Base class for all installation-related events.
 *
 * This abstract class provides the foundation for all installer events,
 * containing common properties like name and version.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
abstract class InstallerEvent
{
    /**
     * Constructor.
     *
     * @param  string  $name  Name of the item (plugin, theme, etc.)
     * @param  string|null  $version  Version of the item
     * @param  string|null  $slug  Slug of the item
     */
    public function __construct(
        public readonly string $name,
        public readonly ?string $version = null,
        public readonly ?string $slug = null
    ) {}
}
