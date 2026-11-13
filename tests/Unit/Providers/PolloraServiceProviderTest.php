<?php

declare(strict_types=1);

use Brain\Monkey\Functions;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Pollora\Application\Infrastructure\Providers\ApplicationServiceProvider;
use Pollora\Foundation\Providers\ArtisanServiceProvider;
use Pollora\Modules\Infrastructure\Providers\ModuleServiceProvider;
use Pollora\Providers\PolloraServiceProvider;
use Pollora\Route\Infrastructure\Providers\RouteServiceProvider;
use Pollora\WordPress\WordPressServiceProvider;

if (! defined('WP_LANG_DIR')) {
    define('WP_LANG_DIR', sys_get_temp_dir().'/pollora-wp-lang');
}

afterEach(fn () => Mockery::close());

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
            ->and(end($registered))->toBe(Pollora\Doctor\Infrastructure\Providers\DoctorServiceProvider::class)
            ->and(array_search(WordPressServiceProvider::class, $registered, true))->toBeGreaterThan(array_search(RouteServiceProvider::class, $registered, true))
            ->and(array_filter($registered, fn (string $p): bool => ! class_exists($p)))->toBe([]);
    });

    it('offers the wp-config bridge for publishing under the public tag', function (): void {
        $app = new Application(sys_get_temp_dir().'/pollora-provider-'.uniqid());
        Functions\when('determine_locale')->justReturn('fr_FR');
        Functions\when('load_textdomain')->justReturn(true);

        (new PolloraServiceProvider($app))->boot();

        $paths = ServiceProvider::pathsToPublish(PolloraServiceProvider::class, 'public');

        expect($paths)->toHaveCount(1)
            ->and(array_key_first($paths))->toEndWith('/public/wp-config.php')
            ->and($paths[array_key_first($paths)])->toBe($app->publicPath());
    });

    it('prefers the translations a site provides over the bundled ones', function (): void {
        $loaded = [];
        Functions\when('determine_locale')->justReturn('fr_FR');
        Functions\when('load_textdomain')->alias(function (string $domain, string $file) use (&$loaded): bool {
            $loaded[] = [$domain, $file];

            return true;
        });

        (new PolloraServiceProvider(new Application(sys_get_temp_dir().'/pollora-provider-'.uniqid())))->boot();

        expect($loaded)->toBe([['pollora', WP_LANG_DIR.'/pollora/pollora-fr_FR.mo']]);
    });

    it('falls back to the bundled translations when the site has none', function (): void {
        $loaded = [];
        Functions\when('determine_locale')->justReturn('de_DE');
        Functions\when('load_textdomain')->alias(function (string $domain, string $file) use (&$loaded): bool {
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
