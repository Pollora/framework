<?php

declare(strict_types=1);

namespace Pollora\Doctor\Infrastructure\Checks;

use Closure;
use Pollora\Doctor\Domain\Contracts\CheckInterface;
use Pollora\Doctor\Domain\Enums\RunContext;
use Pollora\Doctor\Domain\Models\CheckResult;

/**
 * A security plugin does not protect the `/api/` routes unless it is allowed to load there.
 *
 * Requests to `/api/...` load WordPress in lightweight mode: no plugin, except those
 * listed in `wordpress.api_plugins` (empty by default). A firewall, a rate limiter or a
 * JWT authentication plugin that is active in the administration is therefore not
 * running on those routes, and nothing says so: a site that counts on it leaves `/api/` open.
 *
 * Only a warning, never a failure: the plugin may well be useless there, or the
 * protection may live in front of PHP (web server, CDN). The list is short on purpose
 * and keyed on the plugin's directory name, the slug WordPress.org gives it, so it names
 * well-known plugins and does not guess from a description.
 */
final readonly class ApiSecurityPluginsCheck implements CheckInterface
{
    /**
     * Directory names (slugs) of well-known security plugins, with what they do.
     *
     * @var array<string, string>
     */
    public const array KNOWN_PLUGINS = [
        'wordfence' => 'firewall',
        'better-wp-security' => 'firewall',
        'solid-security' => 'firewall',
        'all-in-one-wp-security-and-firewall' => 'firewall',
        'ninjafirewall' => 'firewall',
        'wp-simple-firewall' => 'firewall',
        'wp-cerber' => 'firewall',
        'sucuri-scanner' => 'firewall',
        'limit-login-attempts-reloaded' => 'rate limiter',
        'jwt-authentication-for-wp-rest-api' => 'JWT authentication',
        'jwt-auth' => 'JWT authentication',
        'wp-rest-api-authentication' => 'REST API authentication',
    ];

    /**
     * @param  (Closure(): list<string>)|null  $activePlugins  The active plugins, as `directory/file.php`; the `active_plugins` option answers by default
     */
    public function __construct(private ?Closure $activePlugins = null) {}

    public function id(): string
    {
        return 'api-security-plugins';
    }

    public function label(): string
    {
        return 'Security plugins on /api/ routes';
    }

    public function runsIn(): array
    {
        return [RunContext::Console, RunContext::Http];
    }

    public function run(RunContext $context): CheckResult
    {
        $allowed = config('wordpress.api_plugins');

        if ($allowed === null || in_array('*', (array) $allowed, true)) {
            return CheckResult::skipped('The lightweight mode of /api/ is off: every active plugin loads there.');
        }

        $active = $this->activePluginFiles();

        if ($active === null) {
            return CheckResult::skipped('WordPress is not loaded.');
        }

        $patterns = array_values(array_filter((array) $allowed, is_string(...)));
        $uncovered = [];

        foreach ($active as $file) {
            $slug = $this->slugOf($file);

            if (isset(self::KNOWN_PLUGINS[$slug]) && ! $this->isAllowed($file, $patterns)) {
                $uncovered[$slug] = sprintf('%s (%s)', $slug, self::KNOWN_PLUGINS[$slug]);
            }
        }

        if ($uncovered === []) {
            return CheckResult::ok('No well-known security plugin is left out of the /api/ routes.');
        }

        ksort($uncovered);

        return CheckResult::warning(
            'A security plugin is active, but it does not load on /api/ routes.',
            [
                'Not loaded there: '.implode(', ', $uncovered),
                'Requests to /api/... load no plugin except those in wordpress.api_plugins (config/wordpress.php): a firewall, rate limit or JWT check of that plugin does not run on them.',
            ],
            "List the plugin directories in 'api_plugins' (for example ['".array_key_first($uncovered)."']), or protect /api/ in front of PHP (web server, CDN, Laravel middleware) and leave it as is.",
        );
    }

    /**
     * @return list<string>|null Null when WordPress is not loaded
     */
    private function activePluginFiles(): ?array
    {
        if ($this->activePlugins instanceof Closure) {
            return ($this->activePlugins)();
        }

        if (! function_exists('get_option') || ! function_exists('did_action') || \did_action('plugins_loaded') === 0) {
            return null;
        }

        return array_values(array_filter((array) \get_option('active_plugins', []), is_string(...)));
    }

    /**
     * The directory of a plugin, or the file name of a single-file plugin without its extension.
     */
    private function slugOf(string $pluginFile): string
    {
        $parts = explode('/', $pluginFile);

        return count($parts) > 1 ? $parts[0] : basename($pluginFile, '.php');
    }

    /**
     * Whether the plugin loads on /api/: the same matching as the bootstrap.
     *
     * A pattern is the plugin's directory or its `directory/file.php`; with a `*` it is a glob on the directory.
     *
     * @param  list<string>  $patterns
     */
    private function isAllowed(string $pluginFile, array $patterns): bool
    {
        $directory = dirname($pluginFile);

        foreach ($patterns as $pattern) {
            if ($pattern === $directory || $pattern === $pluginFile) {
                return true;
            }

            if (str_contains($pattern, '*') && fnmatch($pattern, $directory)) {
                return true;
            }
        }

        return false;
    }
}
