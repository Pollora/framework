<?php

declare(strict_types=1);

namespace Pollen\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Pollen\Services\Translater;
use Pollen\Support\Facades\Action;
use Pollen\Support\WordPress;
use Request;

/**
 * Service provider for everything WordPress, configures
 * everything that needs configuring then boots the backend
 * of WordPress.
 *
 * @author Jordan Doyle <jordan@doyle.wf>
 */
class WordPressServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->maybeForceUrlScheme();
        // get the path wordpress is installed in
        define('WP_PATH', json_decode(file_get_contents($this->app->basePath().DIRECTORY_SEPARATOR.'composer.json'),
            true)['extra']['wordpress-install-dir'].'/');

        $this->setConfig();

        $this->ensureAddFilterExists();

        Action::add('after_setup_theme', [$this, 'addThemeSupport']);
        Action::add('widgets_init', [$this, 'registerSidebars']);
        Action::add('after_setup_theme', [$this, 'registerNavMenus']);
    }

    /**
     * Method for requiring the 'plugin.php' file if add_filter function doesn't exist.
     *
     * @return void
     */
    protected function ensureAddFilterExists(): void
    {
        if (function_exists('add_filter')) {
            return;
        }

        require_once ABSPATH.'/wp-includes/plugin.php';
    }

    /**
     * Bootstrap any application services.
     *
     * @SuppressWarnings(PHPMD.CamelCaseVariableName)
     *
     * @return void
     */
    public function boot()
    {
        // WordPress requires $table_prefix rather than another constant.

        $db = DB::getConfig(null);

        $table_prefix = $db['prefix'];
        $this->setDatabaseConstants($db);

        if (! (defined('WP_CLI') && WP_CLI)) {
            require_once ABSPATH.'wp-settings.php';
        }

        // Set up the WordPress query.
        if (! app()->runningInConsole() && ! wp_installing()) {
            wp();
        }

        if (defined('WP_CLI') && WP_CLI) {
            Action::add('init', [$this, 'triggerHooks'], 1);
        } else {
            $this->triggerHooks();
        }

        if (! $this->app->runningInConsole()
            && (defined('WP_ADMIN') || str_contains(Request::server('SCRIPT_NAME'), strrchr(wp_login_url(), '/')))) {
            // disable query caching when in WordPress admin
            config(['wordpress.caching' => 0]);
        }
    }

    /**
     * Forces the URL scheme to HTTPS if it is not already.
     */
    protected function maybeForceUrlScheme()
    {
        if (is_secured()) {
            URL::forceScheme('https');
        }
    }

    /**
     * Register all of the site's theme support.
     *
     * @return void
     */
    public function addThemeSupport()
    {
        collect(config('theme.supports'))->each(function ($value, $key) {
            if (is_string($key)) {
                add_theme_support($key, $value);
            } else {
                add_theme_support($value);
            }
        });
    }

    /**
     * Hacky fix to get network admin working, WordPress is basing the network admin path off of
     * the default site's main link, which obviously doesn't work when the site and WordPress are in
     * separate directories.
     *
     *
     * @return string
     */
    public function rewriteNetworkUrl($url, $path, $scheme)
    {
        if ($scheme === 'relative') {
            $url = WordPress::site()->path;
        } else {
            $url = set_url_scheme((is_secured() ? 'https://' : 'http://').WordPress::site()->domain.WordPress::site()->path, $scheme);
        }

        if ($path && is_string($path)) {
            $url .= str_replace('public/', '', WP_PATH).ltrim($path, '/');
        }

        return $url;
    }

    /**
     * Set up the configuration values that wp-config.php
     * does. Use all the values out of .env instead.
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     *
     * @return void
     */
    protected function setConfig()
    {
        define('WP_DEBUG', config('app.debug'));
        define('WP_DEBUG_DISPLAY', WP_DEBUG);
        define('WP_DEFAULT_THEME', 'koselig');
        define('DISALLOW_FILE_MODS', true);

        $this->setAuthenticationConstants();
        $this->setLocationConstants();
        $this->setMultisiteConstants();

        if ($this->app->runningInConsole()) {
            // allow wordpress to run, even when running from console (ie. artisan compiling)
            $_SERVER['SERVER_PROTOCOL'] = 'https';
            $_SERVER['HTTP_HOST'] = parse_url(config('app.url'))['host'];
        }
    }

    /**
     * WordPress core hooks needed for the main functionality of
     * Pollen.
     *
     * @return void
     */
    public function triggerHooks()
    {
        // register the user's templates
        Action::add('theme_page_templates', function ($pageTemplates) {
            return array_merge($pageTemplates, config('templates'));
        });

        // hacky fix to get network admin working
        Action::add('network_site_url', [$this, 'rewriteNetworkUrl'], 10, 3);
    }

    /**
     * Set all the database constants used by WordPress.
     *
     * @param  string  $tablePrefix
     */
    private function setDatabaseConstants(array $db)
    {
        define('DB_NAME', $db['database']);
        define('DB_USER', $db['username']);
        define('DB_PASSWORD', $db['password']);
        define('DB_HOST', $db['host']);
        define('DB_CHARSET', $db['charset']);
        define('DB_COLLATE', $db['collation']);
        define('DB_PREFIX', $db['prefix']);
    }

    /**
     * Set all the authentication constants used by WordPress.
     */
    private function setAuthenticationConstants()
    {
        define('AUTH_KEY', config('wordpress.auth_key'));
        define('SECURE_AUTH_KEY', config('wordpress.secure_auth_key'));
        define('LOGGED_IN_KEY', config('wordpress.logged_in_key'));
        define('NONCE_KEY', config('wordpress.nonce_key'));
        define('AUTH_SALT', config('wordpress.auth_salt'));
        define('SECURE_AUTH_SALT', config('wordpress.secure_auth_salt'));
        define('LOGGED_IN_SALT', config('wordpress.logged_in_salt'));
        define('NONCE_SALT', config('wordpress.nonce_salt'));
    }

    /**
     * Set constants to let WordPress know where it is in relation to the rest
     * of the site, and move the wp_content directory to something a little more "saner"
     * which sort of hides the fact that we are running WordPress behind the scenes.
     */
    private function setLocationConstants()
    {
        if (! defined('ABSPATH')) {
            define('ABSPATH', $this->app->basePath().DIRECTORY_SEPARATOR.WP_PATH);
        }

        define('WP_SITEURL', url(str_replace('public/', '', WP_PATH)));

        define('WP_HOME', url('/'));

        define('WP_CONTENT_DIR', $this->app->basePath().DIRECTORY_SEPARATOR.'public/content');
        define('WP_CONTENT_URL', url('content'));
    }

    /**
     * Set up constants that will allow the user to use a multisite install of WordPress.
     */
    private function setMultisiteConstants()
    {
        $multisite = config('wordpress.wp_allow_multisite');

        if ($multisite) {
            define('WP_ALLOW_MULTISITE', $multisite);

            $enabled = config('wordpress.multisite');

            if ($enabled) {
                define('MULTISITE', $enabled);
                define('SUBDOMAIN_INSTALL', config('wordpress.subdomain_install'));
                define('DOMAIN_CURRENT_SITE', config('wordpress.domain_current_site'));
                define('PATH_CURRENT_SITE', config('wordpress.path_current_site'));
                define('SITE_ID_CURRENT_SITE', config('wordpress.site_id_current_site'));
                define('BLOG_ID_CURRENT_SITE', config('wordpress.blog_id_current_site'));
            }
        }
    }


    /**
     * Register all of the site's theme sidebars.
     *
     * @return void
     */
    public function registerSidebars()
    {
        $sidebars = config('theme.sidebars');
        $translater = new Translater($sidebars, 'sidebars');
        $sidebars = $translater->translate(['*.name', '*.description']);

        collect($sidebars)->each(function ($value) {
            register_sidebar($value);
        });
    }

    /**
     * Register all of the site's theme menus.
     *
     * @return void
     */
    public function registerNavMenus()
    {
        $menus = config('theme.menus');
        $translater = new Translater($menus, 'menus');
        $menus = $translater->translate(['*']);

        register_nav_menus($menus);
    }
}
