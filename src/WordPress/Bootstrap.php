<?php

declare(strict_types=1);

namespace Pollora\WordPress;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Pollora\Support\Facades\Action;
use Pollora\Support\Facades\Constant;
use Pollora\Support\WordPress;

class Bootstrap
{
    use QueryTrait;

    private array $db;

    public function register(): void
    {
        $this->maybeForceUrlScheme();
        $this->setConfig();
        $this->ensureAddFilterExists();
    }

    public function isOrchestraWorkbench(): bool
    {
        return str_contains(App::basePath(), '/orchestra/');
    }

    public function boot(): void
    {
        $this->db = DB::getConfig(null);
        $this->setDatabaseConstants();

        if ($this->isDatabaseConfigured()) {
            $this->loadWordPressSettings();
        }

        if (app()->runningInConsole() && ! $this->isWordPressInstalled()) {
            define('WP_INSTALLING', true);
        }
        if (! app()->runningInConsole() && $this->isWordPressInstalled()) {
            $this->setupWordPressQuery();
        }

        $this->setupActionHooks();
    }

    private function ensureAddFilterExists(): void
    {
        if (! function_exists('add_filter')) {
            require_once ABSPATH.'/wp-includes/plugin.php';
        }
    }

    private function loadWordPressSettings(): void
    {
        $table_prefix = $this->db['prefix'];

        if (app()->runningInConsole() && ! $this->isWordPressInstalled()) {
            define('SHORTINIT', true);
        }

        if (! app()->runningInWpCli() && ! $this->isOrchestraWorkbench()) {
            require_once ABSPATH.'wp-settings.php';
        }
    }

    private function setupActionHooks(): void
    {
        if (app()->runningInWpCli()) {
            Action::add('init', $this->fixNetworkUrl(...), 1);
        } else {
            $this->fixNetworkUrl();
        }
    }

    private function maybeForceUrlScheme(): void
    {
        if (is_secured()) {
            URL::forceScheme('https');
        }
    }

    public function rewriteNetworkUrl(string $url, string $path, string $scheme): string
    {
        $url = $scheme !== 'relative' ? set_url_scheme(
            (is_secured() ? 'https://' : 'http://').(new WordPress)->site()->domain.(new WordPress)->site()->path,
            $scheme
        ) : (new WordPress)->site()->path;

        if ($path !== '' && $path !== '0') {
            $url .= strtr(WP_PATH, ['public/' => '']).ltrim(
                    Str::of($path)
                        ->replaceMatches('/[^a-zA-Z0-9\-\_\/\.]/', '')
                        ->toString(),
                    '/'
                );
        }

        return $url;
    }

    private function setConfig(): void
    {
        $this->defineWordPressConstants();
        $this->setLocationConstants();

        if (app()->runningInConsole()) {
            $this->setConsoleServerVariables();
        }
    }

    private function defineWordPressConstants(): void
    {
        // Define default constants
        Constant::queue('DISALLOW_FILE_MODS', true);
        Constant::queue('WP_DEBUG', config('app.debug'));
        Constant::queue('WP_DEBUG_DISPLAY', defined('WP_DEBUG') ? WP_DEBUG : config('app.debug'));
        Constant::queue('WP_DEFAULT_THEME', 'default');

        foreach ((array) config('wordpress') as $key => $value) {
            $key = strtoupper($key);
            Constant::queue($key, $value);
        }

        Constant::apply();
    }

    public function fixNetworkUrl(): void
    {
        Action::add('network_site_url', $this->rewriteNetworkUrl(...), 10, 3);
    }

    private function setDatabaseConstants(): void
    {
        // Mapping of WordPress database constants to configuration keys
        $constants = [
            'DB_NAME'     => 'database',
            'DB_USER'     => 'username',
            'DB_PASSWORD' => 'password',
            // For DB_HOST, we will append the port if provided
            'DB_HOST'     => 'host',
            'DB_CHARSET'  => 'charset',
            'DB_COLLATE'  => 'collation',
            'DB_PREFIX'   => 'prefix',
        ];

        foreach ($constants as $constant => $key) {
            if (! defined($constant) && isset($this->db[$key])) {
                // If setting DB_HOST and a port is provided, concatenate host and port
                if ($constant === 'DB_HOST' && isset($this->db['port']) && $this->db['port']) {
                    define($constant, $this->db[$key] . ':' . $this->db['port']);
                } else {
                    define($constant, $this->db[$key]);
                }
            }
        }
    }

    private function setLocationConstants(): void
    {
        define('WP_PATH', 'public/cms/');

        if (! defined('ABSPATH')) {
            if ($this->isOrchestraWorkbench()) { // if Orchestra Platform
                define('ABSPATH', __DIR__.'/../../../../../'.WP_PATH);
            } else {
                define('ABSPATH', App::basePath().DIRECTORY_SEPARATOR.WP_PATH);
            }
        }

        define('WP_SITEURL', url(str_replace('public/', '', WP_PATH)));
        define('WP_HOME', url('/'));
        define('WP_CONTENT_DIR', App::basePath().DIRECTORY_SEPARATOR.'public/content');
        define('WP_CONTENT_URL', url('content'));
    }

    private function setConsoleServerVariables(): void
    {
        $_SERVER['SERVER_PROTOCOL'] = 'https';
        $_SERVER['HTTP_HOST'] = parse_url((string) config('app.url'))['host'];
    }
}
