<?php

declare(strict_types=1);

namespace Pollen\Support;

use WP_Network;
use WP_User;

/**
 * Provides various base WordPress helper functionality in a nice
 * OO way.
 *
 * @author Jordan Doyle <jordan@doyle.wf>
 */
class WordPress
{
    private const DEFAULT_OPTION_VALUE = false;

    /**
     * Check if we are on a multisite, and optionally check the multisite we are on.
     *
     * @param  null|int|array  $id  id (or ids) to check against the site, or null if you want to just check
     *                              if we are actually on a multisite
     */
    public function multisite(null|int|array $id = null): bool
    {
        if (is_array($id)) {
            return array_reduce($id, fn ($carry, $i) => $carry || $this->multisite($i), false);
        }

        return $id === null ? is_multisite() : ($id === $this->getSiteId());
    }

    /**
     * Get a WordPress option from the database.
     *
     * @param  string  $name  name of the option to get
     * @param  mixed  $default  value to return if we don't have a value for the option.
     */
    public function option(string $name, mixed $default = self::DEFAULT_OPTION_VALUE): mixed
    {
        return get_option($name, $default);
    }

    /**
     * Get the current multisite id.
     */
    public function getSiteId(): int
    {
        return get_current_blog_id();
    }

    /**
     * Get the current site that the user is currently browsing.
     */
    public function site(): WP_Network
    {
        return \get_current_site();
    }

    /**
     * Get the current WordPress version, includes WordPress' version.php if it has to.
     *
     * @return mixed
     */
    public function version(): string
    {
        if (! isset($GLOBALS['wp_version'])) {
            require_once ABSPATH.WPINC.'/version.php';
        }

        return $GLOBALS['wp_version'];
    }

    /**
     * Get the current logged in user. Generally, you shouldn't be using this
     * function and should instead be using <code>auth()->user()</code> from Laravel to get
     * the current logged in WordPress user.
     *
     * Use of WP_User is deprecated, however this method will not be removed.
     *
     * @deprecated use <code>auth()->user()</code> instead.
     */
    public function currentUser(): WP_User
    {
        return wp_get_current_user();
    }
}
