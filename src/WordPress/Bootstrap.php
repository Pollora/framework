<?php

declare(strict_types=1);

namespace Pollora\WordPress;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Pollora\Application\Application\Services\ConsoleDetectionService;
use Pollora\Application\Domain\Contracts\DebugDetectorInterface;
use Pollora\Hook\Infrastructure\Services\Action;
use Pollora\Support\Facades\Constant;
use Pollora\Support\WordPress;
use Psr\Container\ContainerInterface;

class Bootstrap
{
    use QueryTrait;

    protected ConsoleDetectionService $consoleDetectionService;

    protected DebugDetectorInterface $debugDetector;

    public function __construct(?ConsoleDetectionService $consoleDetectionService, DebugDetectorInterface $debugDetector)
    {
        $this->consoleDetectionService = $consoleDetectionService ?? app(ConsoleDetectionService::class);
        $this->debugDetector = $debugDetector ?? app(DebugDetectorInterface::class);
    }

    /**
     * Database configuration array.
     */
    private array $db;
    protected \Pollora\Hook\Domain\Contracts\Action $action;

    public function __construct(protected ContainerInterface $app)
    {
        $this->action = $this->app->get(Action::class);
    }

    /**
     * Register Bootstrap configurations.
     */
    public function register(): void
    {
        $this->maybeForceUrlScheme();
        $this->setConfig();
        $this->ensureAddFilterExists();
    }

    /**
     * Boot WordPress and set up configurations.
     */
    public function boot(): void
    {
        $this->db = DB::getConfig(null);
        $this->setDatabaseConstants();

        if ($this->isDatabaseConfigured()) {
            $this->loadWordPressSettings();
        }

        if ($this->consoleDetectionService->isConsole() && ! $this->isWordPressInstalled()) {
            Constant::queue('WP_INSTALLING', true);
            Constant::apply();
        }
        if (! $this->consoleDetectionService->isConsole() && $this->isWordPressInstalled()) {
            $this->runWp();
        }
        $this->setupActionHooks();
    }

    /**
     * Ensure the WordPress add_filter function is available.
     */
    private function ensureAddFilterExists(): void
    {
        if (! function_exists('add_filter')) {
            require_once ABSPATH.'/wp-includes/plugin.php';
        }
    }

    /**
     * Load WordPress settings and initialize core global variables.
     */
    private function loadWordPressSettings(): void
    {
        /**
         * Version information for the current WordPress release.
         *
         * These can't be directly globalized in version.php. When updating,
         * include version.php from another installation and don't override
         * these values if already set.
         *
         * @global string $wp_version             The WordPress version string.
         * @global int    $wp_db_version          WordPress database version.
         * @global string $tinymce_version        TinyMCE version.
         * @global string $required_php_version   The required PHP version string.
         * @global string $required_mysql_version The required MySQL version string.
         * @global string $wp_local_package       Locale code of the package.
         */
        global $wp_version;
        global $wp_db_version;
        global $tinymce_version;
        global $required_php_version;
        global $required_mysql_version;
        global $wp_local_package;

        /**
         * WordPress Hooks and Actions.
         *
         * @global WP_Hook[] $wp_filter          Storage for all hooks registered with WordPress.
         * @global int[]     $wp_actions         Stores the number of times each action has been triggered.
         * @global int[]     $wp_filters         Stores the number of times each filter has been applied.
         * @global string[]  $wp_current_filter  Stack of current filters being executed.
         */
        global $wp_filter;
        global $wp_actions;
        global $wp_filters;
        global $wp_current_filter;

        $table_prefix = $this->db['prefix'];

        if ($this->consoleDetectionService->isConsole() && ! $this->isWordPressInstalled()) {
            define('SHORTINIT', true);
        }

        if (! $this->consoleDetectionService->isWpCli()) {
            require_once ABSPATH.'wp-settings.php';
        }
    }

    /**
     * Set up necessary WordPress action hooks.
     */
    private function setupActionHooks(): void
    {
        if ($this->consoleDetectionService->isWpCli()) {
            Action::add('init', $this->fixNetworkUrl(...), 1);
        } else {
            $this->fixNetworkUrl();
        }
    }

    /**
     * Force HTTPS scheme if the site is secured.
     */
    private function maybeForceUrlScheme(): void
    {
        if (is_secured()) {
            URL::forceScheme('https');
        }
    }

    /**
     * Rewrite network URL based on the given parameters.
     *
     * @param  string  $url  The original URL.
     * @param  string  $path  The requested path.
     * @param  string  $scheme  The scheme (http, https, or relative).
     * @return string The rewritten URL.
     */
    public function rewriteNetworkUrl(string $url, string $path, string $scheme): string
    {
        $url = $scheme !== 'relative' ? set_url_scheme(
            (is_secured() ? 'https://' : 'http://').(new WordPress)->site()->domain.(new WordPress)->site()->path,
            $scheme
        ) : (new WordPress)->site()->path;

        if ($path !== '' && $path !== '0') {
            $url .= strtr('public/cms/', ['public/' => '']).ltrim(
                Str::of($path)
                    ->replaceMatches('/[^a-zA-Z0-9\-\_\/\.]/', '')
                    ->toString(),
                '/'
            );
        }

        return $url;
    }

    /**
     * Set the WordPress configuration constants.
     */
    private function setConfig(): void
    {
        $this->defineWordPressConstants();
        $this->setLocationConstants();

        if ($this->consoleDetectionService->isConsole()) {
            $this->setConsoleServerVariables();
        }
    }

    /**
     * Define WordPress constants.
     */
    private function defineWordPressConstants(): void
    {
        // Define default constants
        Constant::queue('WP_USE_THEMES', ! $this->consoleDetectionService->isConsole() && ! str_starts_with((string) request()->server('REQUEST_URI'), '/cms/'));

        Constant::queue('WP_AUTO_UPDATE_CORE', false);
        // Constant::queue('DISALLOW_FILE_MODS', true);
        // Constant::queue('DISALLOW_FILE_EDIT', true);
        Constant::queue('DISABLE_WP_CRON', true);
        Constant::queue('WP_POST_REVISIONS', 5);

        $debugMode = $this->debugDetector->isDebugMode();
        Constant::queue('WP_DEBUG', $debugMode);
        Constant::queue('WP_DEBUG_DISPLAY', $debugMode);
        Constant::queue('WP_DEFAULT_THEME', 'default');

        Constant::queue('JETPACK_DEV_DEBUG', $debugMode);

        foreach ((array) config('wordpress.constants', []) as $key => $value) {
            $key = strtoupper($key);
            Constant::queue($key, $value);
        }

        Constant::apply();
    }

    /**
     * Fix network URL settings.
     */
    public function fixNetworkUrl(): void
    {
        $this->action->add('network_site_url', $this->rewriteNetworkUrl(...), 10, 3);
    }

    /**
     * Set WordPress database constants based on Laravel configuration.
     */
    private function setDatabaseConstants(): void
    {
        // Mapping of WordPress database constants to configuration keys
        $constants = [
            'DB_NAME' => 'database',
            'DB_USER' => 'username',
            'DB_PASSWORD' => 'password',
            // For DB_HOST, we will append the port if provided
            'DB_HOST' => 'host',
            'DB_CHARSET' => 'charset',
            'DB_COLLATE' => 'collation',
            'DB_PREFIX' => 'prefix',
        ];

        foreach ($constants as $constant => $key) {
            if (! isset($this->db[$key])) {
                continue;
            }

            // If setting DB_HOST and a port is provided, concatenate host and port
            if ($constant === 'DB_HOST' && isset($this->db['port']) && $this->db['port']) {
                Constant::queue($constant, $this->db[$key].':'.$this->db['port']);
            } else {
                Constant::queue($constant, $this->db[$key]);
            }
        }

        Constant::apply();
    }

    private function setLocationConstants(): void
    {
        // Define base paths first to avoid undefined constants
        $wpPath = 'public/cms/';
        $basePath = App::basePath().DIRECTORY_SEPARATOR;
        $contentPath = 'public/content';

        // Queue constants
        Constant::queue('WP_PATH', $wpPath);

        if (! defined('ABSPATH')) {
            Constant::queue('ABSPATH', $basePath.$wpPath);
        }

        Constant::queue('WP_SITEURL', url(str_replace('public/', '', $wpPath)));
        Constant::queue('WP_HOME', url('/'));
        Constant::queue('WP_CONTENT_DIR', $basePath.$contentPath);
        Constant::queue('WP_CONTENT_URL', url('content'));

        // Apply constants once all are queued
        Constant::apply();
    }

    private function setConsoleServerVariables(): void
    {
        $_SERVER['SERVER_PROTOCOL'] = 'https';
        $_SERVER['HTTP_HOST'] = parse_url((string) config('app.url'))['host'];
    }
}
