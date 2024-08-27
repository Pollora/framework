<?php

declare(strict_types=1);

namespace Pollen\WordPress;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\URL;
use Pollen\Support\Facades\Action;
use Pollen\Support\WordPress;

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

    public function isOrchestraWorkbench()
    {
        return str_contains(App::basePath(), '/orchestra/');
    }

    public function boot(): void
    {
        $this->db = DB::getConfig(null);
        $this->setDatabaseConstants();
        $this->loadWordPressSettings();

        if (! App::runningInConsole() && ! wp_installing()) {
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
        if (! (defined('WP_CLI') && WP_CLI) && ! $this->isOrchestraWorkbench()) {
            require_once ABSPATH.'wp-settings.php';
        }
    }

    private function setupActionHooks(): void
    {
        if (defined('WP_CLI') && WP_CLI) {
            Action::add('init', [$this, 'fixNetworkUrl'], 1);
        } else {
            $this->fixNetworkUrl();
        }
    }

    private function isWordPressAdmin(): bool
    {
        return defined('WP_ADMIN') || str_contains(Request::server('SCRIPT_NAME'), strrchr(wp_login_url(), '/'));
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
            (is_secured() ? 'https://' : 'http://').WordPress::site()->domain.WordPress::site()->path,
            $scheme
        ) : WordPress::site()->path;

        if ($path) {
            $url .= str_replace('public/', '', WP_PATH).ltrim($path, '/'); // @phpstan-ignore-line
        }

        return $url;
    }

    private function setConfig(): void
    {
        $this->defineWordPressConstants();
        $this->setWPConstants();
        $this->setLocationConstants();

        if (App::runningInConsole()) {
            $this->setConsoleServerVariables();
        }
    }

    private function defineWordPressConstants(): void
    {
        define('WP_DEBUG', config('app.debug'));
        define('WP_DEBUG_DISPLAY', WP_DEBUG);
        define('WP_DEFAULT_THEME', 'pollen');
        define('DISALLOW_FILE_MODS', true);
    }

    public function fixNetworkUrl(): void
    {
        Action::add('network_site_url', [$this, 'rewriteNetworkUrl'], 10, 3);
    }

    private function setDatabaseConstants(): void
    {
        $constants = [
            'DB_NAME' => 'database',
            'DB_USER' => 'username',
            'DB_PASSWORD' => 'password',
            'DB_HOST' => 'host',
            'DB_CHARSET' => 'charset',
            'DB_COLLATE' => 'collation',
            'DB_PREFIX' => 'prefix',
        ];

        foreach ($constants as $constant => $key) {
            if (! defined($constant) && isset($this->db[$key])) {
                define($constant, $this->db[$key]);
            }
        }
    }

    private function setWPConstants(): void
    {
        foreach ((array) config('wordpress.constants') as $key => $value) {
            $key = strtoupper($key);
            if (! defined($key)) {
                define($key, $value);
            }
        }
    }

    private function setLocationConstants(): void
    {
        define('WP_PATH', 'public/cms/');

        if (! defined('ABSPATH')) {
            if ($this->isOrchestraWorkbench()) { // if Orchestra Platform
                define('ABSPATH', dirname(__FILE__).'/../../../../../'.WP_PATH);
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
        $_SERVER['HTTP_HOST'] = parse_url(config('app.url'))['host'];
    }
}
