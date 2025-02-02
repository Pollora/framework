<?php

declare(strict_types=1);

namespace Pollora\Support;

use WP_Network;
use WP_User;

/**
 * WordPress helper functionality in an object-oriented way.
 *
 * Provides a clean interface to common WordPress functions and features,
 * with proper type hints and documentation.
 *
 * @author Jordan Doyle <jordan@doyle.wf>
 */
class WordPress
{
    /**
     * Default value for options when not found.
     *
     * @var bool
     */
    private const DEFAULT_OPTION_VALUE = false;

    /**
     * Check if we are on a multisite, and optionally check the multisite we are on.
     *
     * @param  null|int|array<int>  $id  ID (or IDs) to check against the site, or null to just check
     *                                   if we are actually on a multisite
     * @return bool True if on multisite and ID matches (if provided)
     */
    public function multisite(null|int|array $id = null): bool
    {
        if (is_array($id)) {
            return array_reduce($id, fn ($carry, $i): bool => $carry || $this->multisite($i), false);
        }

        return $id === null ? is_multisite() : ($id === $this->getSiteId());
    }

    /**
     * Get a WordPress option from the database.
     *
     * @param  string  $name  Name of the option to get
     * @param  mixed  $default  Value to return if option doesn't exist
     * @return mixed The option value or default if not found
     */
    public function option(string $name, mixed $default = self::DEFAULT_OPTION_VALUE): mixed
    {
        return get_option($name, $default);
    }

    /**
     * Get the current multisite ID.
     *
     * @return int Current site ID
     */
    public function getSiteId(): int
    {
        return get_current_blog_id();
    }

    /**
     * Get the current site that the user is browsing.
     *
     * @return \WP_Network Current WordPress network/site object
     */
    public function site(): WP_Network
    {
        return \get_current_site();
    }

    /**
     * Get the current WordPress version.
     *
     * Includes WordPress' version.php if necessary.
     *
     * @return string WordPress version number
     */
    public function version(): string
    {
        if (! isset($GLOBALS['wp_version'])) {
            require_once ABSPATH.WPINC.'/version.php';
        }

        return $GLOBALS['wp_version'];
    }

    /**
     * Get the current logged in user.
     *
     * @deprecated Use auth()->user() instead
     *
     * @return \WP_User Current WordPress user object
     */
    public function currentUser(): WP_User
    {
        return wp_get_current_user();
    }
}
