<?php

declare(strict_types=1);

namespace Pollora\Services\WordPress\Installation;

use Illuminate\Support\Facades\Config;
use Pollora\Support\Facades\Filter;

/**
 * Service for loading WordPress core files and initializing the environment.
 *
 * This service handles the bootstrapping process for WordPress installation,
 * including loading core files, setting up globals, and initializing
 * essential WordPress components.
 */
class WordPressInstallLoaderService
{
    /**
     * List of required WordPress core files.
     *
     * @var array<int, string>
     */
    private const CORE_FILES = [
        '/l10n.php',
        '/class-wp-textdomain-registry.php',
        '/class-wp-walker.php',
        '/theme.php',
        '/cron.php',
        '/kses.php',
        '/user.php',
        '/pluggable.php',
        '/capabilities.php',
        '/blocks.php',
        '/general-template.php',
        '/link-template.php',
        '/rest-api.php',
        '/post.php',
        '/http.php',
        '/rewrite.php',
        '/default-constants.php',
    ];

    /**
     * List of required WordPress classes.
     *
     * @var array<int, string>
     */
    private const CORE_CLASSES = [
        '/class-wp-rewrite.php',
        '/class-wp-theme.php',
        '/class-wp-roles.php',
        '/class-wp-role.php',
        '/class-wp-user.php',
        '/class-wp-post.php',
        '/class-wp-http.php',
        '/class-wp-http-proxy.php',
        '/class-wp-http-response.php',
        '/class-wp-http-requests-hooks.php',
        '/class-wp-http-requests-response.php',
        '/class-wp-block-parser.php',
    ];

    /**
     * Bootstrap the WordPress installation environment.
     *
     * Initializes all necessary components for WordPress installation:
     * - Sets global variables
     * - Loads core files and classes
     * - Loads admin files
     * - Defines constants
     * - Initializes WordPress components
     *
     * @return void
     * @throws \RuntimeException If essential WordPress files cannot be loaded
     */
    public function bootstrap(): void
    {
        $this->setGlobals()
            ->loadCoreFiles()
            ->loadCoreClasses()
            ->loadAdminFiles()
            ->defineConstants()
            ->initializeWordPress();
    }

    /**
     * Set global variables required for WordPress.
     *
     * @return self
     */
    private function setGlobals(): self
    {
        $GLOBALS['locale'] = Config::get('app.locale', 'en_US');

        return $this;
    }

    /**
     * Load WordPress core functionality files.
     *
     * @return self
     * @throws \RuntimeException If core files cannot be loaded
     */
    private function loadCoreFiles(): self
    {
        foreach (self::CORE_FILES as $file) {
            require_once ABSPATH.WPINC.$file;
        }

        return $this;
    }

    /**
     * Load WordPress core class files.
     *
     * @return self
     * @throws \RuntimeException If class files cannot be loaded
     */
    private function loadCoreClasses(): self
    {
        foreach (self::CORE_CLASSES as $file) {
            require_once ABSPATH.WPINC.$file;
        }

        return $this;
    }

    /**
     * Load WordPress admin files required for installation.
     *
     * @return self
     * @throws \RuntimeException If admin files cannot be loaded
     */
    private function loadAdminFiles(): self
    {
        require_once ABSPATH.'wp-admin/includes/upgrade.php';

        return $this;
    }

    /**
     * Define WordPress constants required for installation.
     *
     * @return self
     */
    private function defineConstants(): self
    {
        $appUrl = Config::get('app.url');

        if (! defined('COOKIEHASH')) {
            define('COOKIEHASH', md5((string) $appUrl));
        }

        $cookiePath = $this->getCookiePath($appUrl);

        if (! defined('COOKIEPATH')) {
            define('COOKIEPATH', $cookiePath);
        }

        if (! defined('SITECOOKIEPATH')) {
            define('SITECOOKIEPATH', $cookiePath);
        }

        wp_plugin_directory_constants();
        wp_cookie_constants();

        return $this;
    }

    /**
     * Initialize WordPress components.
     *
     * Sets up text domain registry, permalink structure, and rewrite rules.
     *
     * @return self
     */
    private function initializeWordPress(): self
    {
        // Initialize text domain registry
        $GLOBALS['wp_textdomain_registry'] = new \WP_Textdomain_Registry;
        $GLOBALS['wp_textdomain_registry']->init();

        // Configure permalink structure
        Filter::add('pre_option_permalink_structure', fn (): string => '');

        // Initialize rewrite component
        $GLOBALS['wp_rewrite'] = new \WP_Rewrite;

        return $this;
    }

    /**
     * Get cookie path from application URL.
     *
     * @param string $appUrl The application URL
     * @return string The cookie path
     */
    private function getCookiePath(string $appUrl): string
    {
        return preg_replace('|https?://[^/]+|i', '', $appUrl.'/');
    }
}
