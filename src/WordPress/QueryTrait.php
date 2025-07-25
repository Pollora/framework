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

        $this->action->do('template_redirect');

        // Initialize WordPress for the current request
        wp();

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

        // For normal requests, let Laravel routing handle template resolution
        // Do not load WordPress template-loader.php as we use FrontendController instead
        $this->action->do('pollora_loaded');
    }
}
