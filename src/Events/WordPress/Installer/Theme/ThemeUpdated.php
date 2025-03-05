<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Installer\Theme;

/**
 * Event fired when a theme is updated.
 *
 * This event is triggered when a theme is updated to a new version
 * in WordPress, either through the admin interface or programmatically.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
class ThemeUpdated extends ThemeEvent
{
    /**
     * Constructor.
     *
     * @param string $name Theme name
     * @param string|null $version New theme version
     * @param string|null $slug Theme slug
     * @param string|null $oldVersion Previous theme version
     */
    public function __construct(
        string $name,
        ?string $version = null,
        ?string $slug = null,
        public readonly ?string $oldVersion = null
    ) {
        parent::__construct($name, $version, $slug);
    }
} 