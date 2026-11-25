<?php

declare(strict_types=1);

namespace Pollora\WordPress;

use Exception;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Pollora\WordPress\Exceptions\WordPressNotLoadedException;

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
     * and that a connection can be established. The connection is a MySQL
     * one, or an opted-in SQLite one whose file exists.
     *
     * @return bool True if database is configured, false otherwise
     *
     * @throws Exception
     */
    public function isDatabaseConfigured(): bool
    {
        $config = DB::connection()->getConfig();

        if (! DatabaseSettings::isComplete($config)) {
            return false;
        }

        // A missing SQLite file, or a missing plugin to translate WordPress's SQL, is a
        // project not set up yet, not a failure: WordPress is not loaded, and the
        // setup page and `pollora:doctor` say what to install. Laravel's connector
        // would throw on the file, and MySQL's refused connection does.
        if (DatabaseSettings::isSqlite($config)
            && (! is_file((string) DatabaseSettings::sqlitePath($config)) || ! SqliteIntegration::isInstalled())) {
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
     * like robots.txt, favicon, feeds and trackbacks. Normal template resolution
     * is delegated to the Laravel routing system.
     *
     * The query and `template_redirect` run once every provider has booted,
     * still before routing: WordPress is loaded from this provider's boot, and
     * the application's providers, and the theme's registered by functions.php,
     * boot after it. A REST request, answered by WordPress while it parses the
     * request, then gets the hooks those providers add, and a plugin that
     * renders a page from `template_redirect` (WooCommerce's Review Order 404)
     * gets the theme's view composers and shared data.
     *
     * @throws WordPressNotLoadedException If WordPress core functions are not available
     */
    protected function runWp(): void
    {
        if (! function_exists('wp')) {
            throw WordPressNotLoadedException::coreFunctionsMissing();
        }

        if (! $this->laravelIsServingTheRequest()) {
            $this->action->do('pollora_loaded');

            return;
        }

        App::booted(function (): void {
            $this->withWordPressErrorHandling(function (): void {
                // Initialize WordPress for the current request
                wp();

                $this->handleTemplateRedirect();

                // For normal requests, let Laravel routing handle template resolution
                // Do not load WordPress template-loader.php as we use FrontendController instead
                $this->action->do('pollora_loaded');
            });
        });
    }

    /**
     * Fire `template_redirect`, then answer the requests WordPress serves itself.
     */
    private function handleTemplateRedirect(): void
    {
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
            // wp-trackback.php is written for the global scope: without $wp it
            // loads WordPress a second time, and it reads $posts and $wpdb.
            global $wp, $wpdb, $posts;

            require_once ABSPATH.'wp-trackback.php';
            exit;
        }
    }

    /**
     * Run a callback with WordPress-safe error handling.
     */
    abstract private function withWordPressErrorHandling(callable $callback): void;

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
     *
     * A server may start another script that includes the front controller:
     * Valet and Herd run their own `server.php`, which requires
     * `public/index.php`, so `SCRIPT_FILENAME` is not the front controller
     * there. The request is still Laravel's, and a front controller PHP has
     * included proves it — WordPress's entry points never include it.
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

        return $running === $frontController
            || in_array($frontController, array_map(realpath(...), get_included_files()), true);
    }
}
