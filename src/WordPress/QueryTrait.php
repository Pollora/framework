<?php

declare(strict_types=1);

namespace Pollora\WordPress;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Trait providing WordPress query functionality.
 *
 * This trait contains methods for interacting with WordPress core,
 * checking database configuration, and handling WordPress requests.
 */
trait QueryTrait
{
    /**
     * Check if the database is properly configured.
     *
     * Verifies that the database connection settings are valid
     * and that a connection can be established.
     *
     * @return bool True if database is configured, false otherwise
     *
     * @throws Exception
     */
    public function isDatabaseConfigured(): bool
    {
        $config = DB::connection()->getConfig();

        $dbSettingsFilled = $config['driver'] === 'mysql'
            && isset($config['host'])
            && isset($config['username'])
            && isset($config['password'])
            && isset($config['database']);

        if (! $dbSettingsFilled) {
            return false;
        }

        DB::connection()->getPdo();

        return true;
    }

    /**
     * Check if WordPress is installed by verifying the presence of core database tables and required content.
     *
     * @return bool True if WordPress is installed, false otherwise.
     */
    private function isWordPressInstalled(): bool
    {
        // Use the built-in WordPress function if available
        if (function_exists('is_blog_installed')) {
            return is_blog_installed();
        }

        if (! $this->isDatabaseConfigured()) {
            return false;
        }

        // Fallback to direct database check
        try {
            return Schema::hasTable('options') && DB::table('options')
                ->where('option_name', 'siteurl')
                ->exists();
        } catch (Exception) {
            return false;
        }
    }

    /**
     * Run the WordPress bootstrap process.
     *
     * This method initializes WordPress and handles special request types
     * like robots.txt, favicon, feeds, trackbacks, and AJAX requests. AJAX requests
     * are handled early and bypass the normal WordPress query processing to prevent
     * them from reaching the Laravel routing system. Normal template resolution
     * is delegated to the Laravel routing system.
     *
     * @throws \RuntimeException If WordPress core functions are not available
     */
    protected function runWp(): void
    {
        if (! function_exists('wp')) {
            throw new \RuntimeException('The WordPress core functions are not available. Ensure WordPress is loaded.');
        }

        if ($this->laravelIsServingTheRequest()) {
            // Initialize WordPress for the current request
            wp();

            if (wp_using_themes()) {
                $this->action->do('template_redirect');
            }

            // Handle special request types that should bypass Laravel routing
            if (is_robots()) {
                $this->action->do('do_robots');
                exit;
            }

            if (is_favicon()) {
                $this->action->do('do_favicon');
                exit;
            }

            if (is_feed()) {
                do_feed();
                exit;
            }

            if (is_trackback()) {
                require_once ABSPATH.'wp-trackback.php';
                exit;
            }
        }

        // For normal requests, let Laravel routing handle template resolution
        // Do not load WordPress template-loader.php as we use FrontendController instead
        $this->action->do('pollora_loaded');
    }

    /**
     * Whether this request is Laravel's to serve.
     *
     * WordPress has entry points of its own — wp-login.php, wp-admin, xmlrpc.php
     * — and PHP runs those files directly; Pollora only happens to be loaded
     * along the way, through wp-config.php. Resolving the URL as a content
     * request there answers nothing and does one real harm: `/cms/wp-login.php`
     * matches the attachment rewrite rule (`[^/]+/([^/]+)/?$`), WordPress finds
     * no attachment by that name, and sends a 404 header before the login form
     * it is about to render. Measured: the login page answered 404 with a
     * perfectly good body, and `/cms/` and `/cms/wp-links-opml.php` with it.
     *
     * The test is which script PHP is running, not a list of filenames: any
     * entry point WordPress grows later is covered without being named, and a
     * project adding its own is too.
     */
    private function laravelIsServingTheRequest(): bool
    {
        $script = $_SERVER['SCRIPT_FILENAME'] ?? '';

        if (! is_string($script) || $script === '') {
            // Nothing to compare against: behave as before rather than skip
            // work the request may depend on.
            return true;
        }

        try {
            $frontController = realpath(public_path('index.php'));
        } catch (\Throwable) {
            // public_path() needs a bound application; without one there is no
            // front controller to compare against.
            return true;
        }

        $running = realpath($script);

        if ($running === false || $frontController === false) {
            return true;
        }

        return $running === $frontController;
    }
}
