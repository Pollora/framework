<?php

declare(strict_types=1);

namespace Pollen\Support;

use Pollen\Models\User;

/**
 * Provides various base WordPress helper functionality in a nice
 * OO way.
 *
 * @author Jordan Doyle <jordan@doyle.wf>
 */
class WordPress
{
    /**
     * Check if we are on a multisite, and optionally check the multisite we are on.
     *
     * @param  null|int|array  $id  id (or ids) to check against the site, or null if you want to just check
     *                              if we are actually on a multisite
     * @return bool
     */
    public static function multisite($id = null)
    {
        if (is_array($id)) {
            foreach ($id as $i) {
                if (static::multisite($i)) {
                    return true;
                }
            }
        }

        return $id === null ? is_multisite() : ($id === static::getSiteId());
    }

    /**
     * Get a WordPress option from the database.
     *
     * @param  string  $name  name of the option to get
     * @param  mixed  $default  value to return if we don't have a value for the option.
     * @return mixed
     */
    public static function option($name, $default = false)
    {
        return get_option($name, $default);
    }

    /**
     * Get the current multisite id.
     *
     * @return int
     */
    public static function getSiteId()
    {
        return get_current_blog_id();
    }

    /**
     * Get the current site that the user is currently browsing.
     *
     * @return \WP_Network
     */
    public static function site()
    {
        return \get_current_site();
    }

    /**
     * Get the current WordPress version, includes WordPress' version.php if it has to.
     *
     * @return mixed
     */
    public static function version()
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
     *
     * @return \WP_User
     */
    public static function currentUser()
    {
        return wp_get_current_user();
    }
}
