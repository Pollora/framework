<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Pollora\Application\Infrastructure\Providers\ApplicationServiceProvider;
use Pollora\Doctor\Infrastructure\Providers\DoctorServiceProvider;
use Pollora\Foundation\Providers\ArtisanServiceProvider;
use Pollora\Modules\Infrastructure\Providers\ModuleServiceProvider;
use Pollora\Providers\PolloraServiceProvider;
use Pollora\Route\Infrastructure\Providers\RouteServiceProvider;
use Pollora\WordPress\WordPressServiceProvider;

if (! defined('WP_LANG_DIR')) {
    define('WP_LANG_DIR', sys_get_temp_dir().'/pollora-wp-lang');
}

/**
 * What the text domain loading asks of WordPress.
 *
 * The two WordPress functions below are real, not Brain Monkey stubs: a stub
 * defined during a test outlives it, and the first test that checks
 * function_exists('determine_locale') afterwards would call a stub with no
 * expectation. With the defaults reset after each test, they behave as
 * WordPress does when nothing was customised: an en_US site, no translation
 * file loaded.
 */
final class PolloraTextDomainProbe
{
    public static string $locale = 'en_US';

    /** @var null|callable(string, string): bool */
    public static $loader;

    public static function reset(): void
    {
        self::$locale = 'en_US';
        self::$loader = null;
    }
}

if (! function_exists('determine_locale')) {
    function determine_locale(): string
    {
        return PolloraTextDomainProbe::$locale;
    }
}

if (! function_exists('load_textdomain')) {
    function load_textdomain(string $domain, string $mofile): bool
    {
        return PolloraTextDomainProbe::$loader === null ? false : (PolloraTextDomainProbe::$loader)($domain, $mofile);
    }
}

afterEach(function (): void {
    PolloraTextDomainProbe::reset();
    Mockery::close();
});

describe('PolloraServiceProvider', function (): void {
    it('registers every framework provider exactly once, in a stable order', function (): void {
        $registered = [];
        $app = Mockery::mock(Application::class);
        $app->shouldReceive('register')->andReturnUsing(function (string $provider) use (&$registered) {
            $registered[] = $provider;

            return Mockery::mock(ServiceProvider::class);
        });

        (new PolloraServiceProvider($app))->register();

        expect($registered)->toHaveCount(count(array_unique($registered)))
            ->and($registered)->toContain(ModuleServiceProvider::class, ArtisanServiceProvider::class, RouteServiceProvider::class)
            ->and($registered[0])->toBe(ApplicationServiceProvider::class)
            ->and(end($registered))->toBe(DoctorServiceProvider::class)
            ->and(array_search(WordPressServiceProvider::class, $registered, true))->toBeGreaterThan(array_search(RouteServiceProvider::class, $registered, true))
            ->and(array_filter($registered, fn (string $p): bool => ! class_exists($p)))->toBe([]);
    });

    it('offers the wp-config bridge for publishing under the public tag', function (): void {
        $app = new Application(sys_get_temp_dir().'/pollora-provider-'.uniqid());

        (new PolloraServiceProvider($app))->boot();

        $paths = ServiceProvider::pathsToPublish(PolloraServiceProvider::class, 'public');

        expect($paths)->toHaveCount(1)
            ->and(array_key_first($paths))->toEndWith('/public/wp-config.php')
            ->and($paths[array_key_first($paths)])->toBe($app->publicPath());
    });

    it('prefers the translations a site provides over the bundled ones', function (): void {
        $loaded = [];
        PolloraTextDomainProbe::$locale = 'fr_FR';
        PolloraTextDomainProbe::$loader = (function (string $domain, string $file) use (&$loaded): bool {
            $loaded[] = [$domain, $file];

            return true;
        });

        (new PolloraServiceProvider(new Application(sys_get_temp_dir().'/pollora-provider-'.uniqid())))->boot();

        expect($loaded)->toBe([['pollora', WP_LANG_DIR.'/pollora/pollora-fr_FR.mo']]);
    });

    it('falls back to the bundled translations when the site has none', function (): void {
        $loaded = [];
        PolloraTextDomainProbe::$locale = 'de_DE';
        PolloraTextDomainProbe::$loader = (function (string $domain, string $file) use (&$loaded): bool {
            $loaded[] = $file;

            return false;
        });

        (new PolloraServiceProvider(new Application(sys_get_temp_dir().'/pollora-provider-'.uniqid())))->boot();

        expect($loaded)->toHaveCount(2)
            ->and($loaded[0])->toBe(WP_LANG_DIR.'/pollora/pollora-de_DE.mo')
            ->and($loaded[1])->toEndWith('/resources/languages/pollora-de_DE.mo')
            ->and(file_exists($loaded[1]))->toBeTrue();
    });
});
