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

require_once __DIR__.'/../wordpress-i18n-stand-ins.php';

/*
 * loadFrameworkTextDomain() remembers the locales it has already tried for the whole process, and does nothing
 * when the catalogue is loaded: the locales used here (it_IT, nl_NL) are used by no other test, and the
 * catalogue is forgotten before each test.
 */
beforeEach(function (): void {
    unset($GLOBALS['l10n']['pollora']);
});

afterEach(function (): void {
    WordPressI18nStandIn::reset();
    unset($GLOBALS['l10n']['pollora']);
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

    it('offers nothing for publishing whose source file is missing from the framework', function (): void {
        (new PolloraServiceProvider(new Application(sys_get_temp_dir().'/pollora-provider-'.uniqid())))->boot();

        $missing = array_filter(
            array_keys(ServiceProvider::pathsToPublish(PolloraServiceProvider::class)),
            fn (string $source): bool => ! file_exists($source),
        );

        expect($missing)->toBe([])
            ->and(ServiceProvider::pathsToPublish(PolloraServiceProvider::class, 'public'))->toBe([]);
    });

    it('prefers the translations a site provides over the bundled ones', function (): void {
        $loaded = [];
        WordPressI18nStandIn::$locale = 'it_IT';
        WordPressI18nStandIn::$loader = (function (string $domain, string $file) use (&$loaded): bool {
            $loaded[] = [$domain, $file];

            return true;
        });

        (new PolloraServiceProvider(new Application(sys_get_temp_dir().'/pollora-provider-'.uniqid())))->boot();

        expect($loaded)->toBe([['pollora', WP_LANG_DIR.'/pollora/pollora-it_IT.mo']]);
    });

    it('falls back to the bundled translations when the site has none', function (): void {
        $loaded = [];
        WordPressI18nStandIn::$locale = 'nl_NL';
        WordPressI18nStandIn::$loader = (function (string $domain, string $file) use (&$loaded): bool {
            $loaded[] = $file;

            return false;
        });

        (new PolloraServiceProvider(new Application(sys_get_temp_dir().'/pollora-provider-'.uniqid())))->boot();

        expect($loaded)->toHaveCount(2)
            ->and($loaded[0])->toBe(WP_LANG_DIR.'/pollora/pollora-nl_NL.mo')
            ->and($loaded[1])->toEndWith('/resources/languages/pollora-nl_NL.mo')
            ->and(file_exists($loaded[1]))->toBeTrue();
    });
});
