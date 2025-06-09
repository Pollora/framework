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

        try {
            DB::connection()->getPdo();
        } catch (\Exception) {
            return false;
        }

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

        // Check for AJAX requests BEFORE calling wp() to prevent them from going through normal WordPress query processing
        if ($this->isAjaxRequest()) {
            $this->handleCustomAjaxRequest();

            return; // Exit early, don't process through normal WordPress flow
        }

        // Initialize WordPress for the current request (only for non-AJAX requests)
        wp();

        // Handle special request types that should bypass Laravel routing
        if (is_robots()) {
            do_action('do_robots');
            exit;
        }
        if (is_favicon()) {
            do_action('do_favicon');
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

        // For normal requests, let Laravel routing handle template resolution
        // Do not load WordPress template-loader.php as we use FrontendController instead

        do_action('pollora_loaded');
    }

    /**
     * Check if the current request is an AJAX request.
     *
     * @return bool True if this is an AJAX request, false otherwise
     */
    private function isAjaxRequest(): bool
    {
        // Check if DOING_AJAX constant is already defined (set by admin-ajax.php)
        if (defined('DOING_AJAX') && DOING_AJAX) {
            return true;
        }

        // Check if this request is targeting admin-ajax.php
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        if (str_contains($requestUri, 'wp-admin/admin-ajax.php')) {
            return true;
        }

        // Check for AJAX action parameter
        if (isset($_REQUEST['action']) && str_contains($requestUri, '/cms/')) {
            return true;
        }

        // Check for XMLHttpRequest header (set by most AJAX libraries)
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            return true;
        }

        return false;
    }

    /**
     * Handle AJAX requests using WordPress native AJAX system.
     *
     * This method ensures AJAX requests are processed by WordPress's admin-ajax.php
     * and do not go through the normal template loading system.
     */
    private function handleCustomAjaxRequest(): void
    {
        $this->action->do('template_redirect');
    }
}
