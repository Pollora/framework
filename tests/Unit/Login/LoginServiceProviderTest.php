<?php

declare(strict_types=1);

use Illuminate\Config\Repository;
use Illuminate\Foundation\Application;
use Pollora\Hook\Domain\Contract\Action;
use Pollora\Hook\Domain\Contract\Filter;
use Pollora\Login\Domain\Contracts\DesignSettingsRepository;
use Pollora\Login\Domain\Models\LoginConfiguration;
use Pollora\Login\Domain\Models\LoginStylesheet;
use Pollora\Login\Infrastructure\Providers\LoginServiceProvider;
use Pollora\Login\Infrastructure\Repositories\ThemeJsonDesignSettings;
use Pollora\Login\Infrastructure\Services\LoginScreen;
use Pollora\Login\Infrastructure\Services\LogoResolver;

/**
 * The provider decides two things that matter more than what it binds: that
 * nothing is hooked on a site that did not ask, and that nothing is hooked on
 * a request that is not a login request.
 */
function applicationWith(?array $loginConfig, bool $withHooks = true): Application
{
    Brain\Monkey\Functions\when('get_stylesheet_directory')->justReturn('/srv/site/themes/apiary');
    Brain\Monkey\Functions\when('wp_get_global_settings')->justReturn([]);
    Brain\Monkey\Functions\when('get_theme_mod')->justReturn(false);

    $app = new Application;
    $app->instance('config', new Repository(['theme' => ['login' => $loginConfig]]));

    if ($withHooks) {
        $action = Mockery::mock(Action::class);
        $action->shouldReceive('add')->andReturnSelf()->byDefault();
        $app->instance(Action::class, $action);

        $filter = Mockery::mock(Filter::class);
        $filter->shouldReceive('add')->andReturnSelf()->byDefault();
        $filter->shouldReceive('apply')->andReturnUsing(fn (string $hook, mixed $value): mixed => $value)->byDefault();
        $app->instance(Filter::class, $filter);
    }

    return $app;
}

describe('LoginServiceProvider', function (): void {
    it('binds what the login screen is made of', function (): void {
        $app = applicationWith(['enabled' => true]);
        (new LoginServiceProvider($app))->register();

        expect($app->make(DesignSettingsRepository::class))->toBeInstanceOf(ThemeJsonDesignSettings::class)
            ->and($app->make(LoginConfiguration::class)->enabled)->toBeTrue()
            ->and($app->make(LogoResolver::class))->toBeInstanceOf(LogoResolver::class)
            ->and($app->make(LoginStylesheet::class))->toBeInstanceOf(LoginStylesheet::class)
            ->and($app->make(LoginScreen::class))->toBeInstanceOf(LoginScreen::class);
    });

    it('ships the stylesheet it promises', function (): void {
        // A path that resolves in the repository and not in an installed
        // package would leave every site with tokens and no rules.
        $app = applicationWith(['enabled' => true]);
        (new LoginServiceProvider($app))->register();

        expect($app->make(LoginScreen::class)->css())->toContain('.pollora-login form');
    });

    it('reads a theme that ships no login config as no customisation', function (): void {
        $app = applicationWith(null);
        (new LoginServiceProvider($app))->register();

        expect($app->make(LoginConfiguration::class)->enabled)->toBeFalse();
    });

    it('ignores a config that is not a config', function (): void {
        $app = applicationWith(null);
        $app->make('config')->set('theme.login', 'nonsense');
        (new LoginServiceProvider($app))->register();

        expect($app->make(LoginConfiguration::class)->enabled)->toBeFalse();
    });

    it('waits for WordPress to announce a login request', function (): void {
        $app = applicationWith(['enabled' => true]);
        $app->make(Action::class)->shouldReceive('add')->once()
            ->with('login_init', Mockery::type('callable'))->andReturnSelf();

        $provider = new LoginServiceProvider($app);
        $provider->register();
        $provider->boot();
    });

    it('stays silent where the hook system is not bound', function (): void {
        $app = applicationWith(['enabled' => true], withHooks: false);

        $provider = new LoginServiceProvider($app);
        $provider->register();
        $provider->boot();
    })->throwsNoExceptions();

    it('hooks every screen in the login family', function (): void {
        $app = applicationWith(['enabled' => true]);
        $provider = new LoginServiceProvider($app);
        $provider->register();

        $actions = [];
        $filters = [];
        $action = $app->make(Action::class);
        $filter = $app->make(Filter::class);
        $action->shouldReceive('add')->andReturnUsing(function (string $hook) use (&$actions, $action) {
            $actions[] = $hook;

            return $action;
        });
        $filter->shouldReceive('add')->andReturnUsing(function (string $hook) use (&$filters, $filter) {
            $filters[] = $hook;

            return $filter;
        });

        $provider->hookLoginScreen();

        expect($actions)->toBe(['login_head', 'login_footer'])
            ->and($filters)->toBe(['login_body_class', 'login_headerurl', 'login_headertext']);
    });

    it('hooks nothing for a theme that switched it off', function (): void {
        $app = applicationWith(['enabled' => false]);
        $provider = new LoginServiceProvider($app);
        $provider->register();

        $app->make(Action::class)->shouldNotReceive('add');
        $app->make(Filter::class)->shouldNotReceive('add');

        $provider->hookLoginScreen();
    });

    it('declares everything it binds', function (): void {
        $app = applicationWith(['enabled' => true]);
        $provider = new LoginServiceProvider($app);
        $provider->register();

        foreach ($provider->provides() as $service) {
            expect($app->bound($service))->toBeTrue();
        }
    });
});
