<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Installer;

/**
 * Event fired when WordPress core is updated.
 *
 * This event is triggered when WordPress core is updated to a new version,
 * either through the admin interface or automatically.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
class WordPressUpdated
{
    /**
     * Constructor.
     *
     * @param  string  $newVersion  New WordPress version
     * @param  string  $oldVersion  Previous WordPress version
     * @param  bool  $autoUpdated  Whether the update was automatic
     */
    public function __construct(
        public readonly string $newVersion,
        public readonly string $oldVersion,
        public readonly bool $autoUpdated
    ) {}
}
