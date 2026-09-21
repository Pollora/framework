<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Pollora\Theme\Application\Services\ThemeAvailability;
use Pollora\Theme\Domain\Contracts\ThemeModuleInterface;
use Pollora\Theme\Domain\Contracts\ThemeRegistrarInterface;
use Pollora\Theme\UI\Http\MissingThemeNotice;
use Pollora\Theme\UI\Http\MissingThemePage;
use Symfony\Component\HttpFoundation\Response;

/**
 * @param  ThemeModuleInterface|null  $active  The theme the registrar reports.
 */
function themeAvailability(?ThemeModuleInterface $active): ThemeAvailability
{
    $registrar = Mockery::mock(ThemeRegistrarInterface::class);
    $registrar->shouldReceive('getActiveTheme')->andReturn($active);

    $container = new Container;
    $container->instance(ThemeRegistrarInterface::class, $registrar);

    return new ThemeAvailability($container);
}

function viewNotFound(string $view = 'home'): InvalidArgumentException
{
    return new InvalidArgumentException("View [{$view}] not found.");
}

describe('ThemeAvailability', function (): void {
    it('reports a theme as present when one is registered', function (): void {
        $theme = Mockery::mock(ThemeModuleInterface::class);

        expect(themeAvailability($theme)->isMissing())->toBeFalse();
    });

    it('reports a theme as missing when none is registered and no directory exists', function (): void {
        Brain\Monkey\Functions\when('get_stylesheet_directory')
            ->justReturn(sys_get_temp_dir().'/pollora-theme-that-does-not-exist');

        expect(themeAvailability(null)->isMissing())->toBeTrue();
    });

    it('trusts the theme directory when WordPress points at a real one', function (): void {
        Brain\Monkey\Functions\when('get_stylesheet_directory')->justReturn(sys_get_temp_dir());

        expect(themeAvailability(null)->isMissing())->toBeFalse();
    });
});

describe('MissingThemePage', function (): void {
    it('answers a missing view with setup instructions when there is no theme', function (): void {
        Brain\Monkey\Functions\when('get_stylesheet_directory')->justReturn('/nope');

        $page = new MissingThemePage(themeAvailability(null));
        $response = $page->handle(viewNotFound(), Request::create('/'));

        expect($response)->toBeInstanceOf(Response::class)
            ->and($response->getStatusCode())->toBe(503)
            ->and($response->getContent())->toContain('pollora/theme-default')
            ->and($response->getContent())->toContain('pollora/theme-apiary');
    });

    it('leaves the exception alone when a theme is installed', function (): void {
        $theme = Mockery::mock(ThemeModuleInterface::class);

        $page = new MissingThemePage(themeAvailability($theme));

        expect($page->handle(viewNotFound(), Request::create('/')))->toBeNull();
    });

    it('leaves other exceptions alone', function (): void {
        Brain\Monkey\Functions\when('get_stylesheet_directory')->justReturn('/nope');

        $page = new MissingThemePage(themeAvailability(null));

        expect($page->handle(new RuntimeException('database is down'), Request::create('/')))->toBeNull();
    });

    it('stays out of the way of the admin and the API', function (): void {
        Brain\Monkey\Functions\when('get_stylesheet_directory')->justReturn('/nope');

        $page = new MissingThemePage(themeAvailability(null));

        foreach (['/wp-admin/', '/cms/wp-admin/options.php', '/wp-login.php', '/api/posts', '/wp-json/wp/v2/posts'] as $path) {
            expect($page->handle(viewNotFound(), Request::create($path)))->toBeNull();
        }
    });

    /*
     * Since the skeleton stopped declaring WordPress routes, the template
     * hierarchy decides and nothing calls view(), so handle() above never
     * fires: a theme-less site answers a bare 404 instead. These cover the
     * path that takes the request over before a template is chosen.
     */
    it('takes over a front-end request when there is no theme', function (): void {
        Brain\Monkey\Functions\when('get_stylesheet_directory')->justReturn('/nope');
        Brain\Monkey\Functions\when('is_admin')->justReturn(false);
        Brain\Monkey\Functions\when('wp_doing_ajax')->justReturn(false);

        $page = new MissingThemePage(themeAvailability(null));
        $response = $page->responseForFrontEndRequest();

        expect($response)->toBeInstanceOf(Response::class)
            ->and($response->getStatusCode())->toBe(503)
            ->and($response->getContent())->toContain('pollora:make:theme');
    });

    it('leaves a front-end request alone when a theme is installed', function (): void {
        Brain\Monkey\Functions\when('is_admin')->justReturn(false);
        Brain\Monkey\Functions\when('wp_doing_ajax')->justReturn(false);

        $theme = Mockery::mock(ThemeModuleInterface::class);
        $page = new MissingThemePage(themeAvailability($theme));

        expect($page->responseForFrontEndRequest())->toBeNull();
    });

    it('leaves the admin alone even with no theme, since that is where it gets fixed', function (): void {
        Brain\Monkey\Functions\when('get_stylesheet_directory')->justReturn('/nope');
        Brain\Monkey\Functions\when('is_admin')->justReturn(true);
        Brain\Monkey\Functions\when('wp_doing_ajax')->justReturn(false);

        $page = new MissingThemePage(themeAvailability(null));

        expect($page->responseForFrontEndRequest())->toBeNull();
    });
});

describe('MissingThemeNotice', function (): void {
    it('warns in the admin when no theme is installed', function (): void {
        Brain\Monkey\Functions\when('get_stylesheet_directory')->justReturn('/nope');

        ob_start();
        (new MissingThemeNotice(themeAvailability(null)))->render();
        $output = (string) ob_get_clean();

        expect($output)->toContain('notice-warning')
            ->and($output)->toContain('no theme installed')
            ->and($output)->toContain('pollora:make:theme');
    });

    it('stays silent once a theme is installed', function (): void {
        $theme = Mockery::mock(ThemeModuleInterface::class);

        ob_start();
        (new MissingThemeNotice(themeAvailability($theme)))->render();
        $output = (string) ob_get_clean();

        expect($output)->toBeEmpty();
    });
});
