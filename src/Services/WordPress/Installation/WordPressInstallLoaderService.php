<?php

declare(strict_types=1);

namespace Pollora\Services\WordPress\Installation;

use Illuminate\Support\Facades\Config;
use Pollora\Support\Facades\Filter;

class WordPressInstallLoaderService
{
    /**
     * List of required WordPress core files
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
     * List of required WordPress classes
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

    public function bootstrap(): void
    {
        $this->setGlobals()
            ->loadCoreFiles()
            ->loadCoreClasses()
            ->loadAdminFiles()
            ->defineConstants()
            ->initializeWordPress();
    }

    private function setGlobals(): self
    {
        $GLOBALS['locale'] = Config::get('app.locale', 'en_US');

        return $this;
    }

    private function loadCoreFiles(): self
    {
        foreach (self::CORE_FILES as $file) {
            require_once ABSPATH.WPINC.$file;
        }

        return $this;
    }

    private function loadCoreClasses(): self
    {
        foreach (self::CORE_CLASSES as $file) {
            require_once ABSPATH.WPINC.$file;
        }

        return $this;
    }

    private function loadAdminFiles(): self
    {
        require_once ABSPATH.'wp-admin/includes/upgrade.php';

        return $this;
    }

    private function defineConstants(): self
    {
        $appUrl = Config::get('app.url');

        if (! defined('COOKIEHASH')) {
            define('COOKIEHASH', md5($appUrl));
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

    private function initializeWordPress(): self
    {
        // Initialize text domain registry
        $GLOBALS['wp_textdomain_registry'] = new \WP_Textdomain_Registry;
        $GLOBALS['wp_textdomain_registry']->init();

        // Configure permalink structure
        Filter::add('pre_option_permalink_structure', fn () => '');

        // Initialize rewrite component
        $GLOBALS['wp_rewrite'] = new \WP_Rewrite;

        return $this;
    }

    private function getCookiePath(string $appUrl): string
    {
        return preg_replace('|https?://[^/]+|i', '', $appUrl.'/');
    }
}
