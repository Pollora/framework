<?php

declare(strict_types=1);

namespace Pollora\Login\Infrastructure\Providers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Pollora\Hook\Domain\Contract\Action;
use Pollora\Hook\Domain\Contract\Filter;
use Pollora\Login\Domain\Contracts\DesignSettingsRepository;
use Pollora\Login\Domain\Models\LoginConfiguration;
use Pollora\Login\Domain\Models\LoginStylesheet;
use Pollora\Login\Infrastructure\Repositories\ThemeJsonDesignSettings;
use Pollora\Login\Infrastructure\Services\LoginScreen;
use Pollora\Login\Infrastructure\Services\LogoResolver;

/**
 * Wires the login screen customisation, and stays out of the way otherwise.
 *
 * Nothing is hooked until WordPress announces a login request. `login_init`
 * is the first thing wp-login.php fires and it runs on every screen in that
 * family, so one registration covers sign-in, lost password, reset and
 * register; every other request pays a single added action and nothing else.
 *
 * Opt-in by design: a theme with no config/login.php is left alone. Upgrading
 * the framework must not restyle the screen people log into.
 */
class LoginServiceProvider extends ServiceProvider
{
    /**
     * Where the stylesheet ships inside the package.
     */
    private const string STYLESHEET = __DIR__.'/../../../../resources/css/login.css';

    public function register(): void
    {
        $this->app->singleton(DesignSettingsRepository::class, fn (): ThemeJsonDesignSettings => new ThemeJsonDesignSettings);

        $this->app->singleton(function (Application $app): LoginConfiguration {
            $config = $app->make('config')->get('theme.login');

            return LoginConfiguration::fromArray(is_array($config) ? $config : null);
        });

        // Resolved late, inside the login request: get_stylesheet_directory()
        // answers nothing useful before the theme is set up.
        $this->app->singleton(LogoResolver::class, fn (): LogoResolver => new LogoResolver(
            function_exists('get_stylesheet_directory') ? (string) \get_stylesheet_directory() : ''
        ));

        $this->app->singleton(LoginStylesheet::class, fn (): LoginStylesheet => new LoginStylesheet($this->rules()));

        $this->app->singleton(LoginScreen::class, fn (Application $app): LoginScreen => new LoginScreen(
            $app->make(LoginConfiguration::class),
            $app->make(DesignSettingsRepository::class),
            $app->make(LogoResolver::class),
            $app->make(LoginStylesheet::class),
            $app->make(Filter::class),
        ));
    }

    public function boot(): void
    {
        if (! $this->app->bound(Action::class)) {
            return;
        }

        $this->app->make(Action::class)->add('login_init', $this->hookLoginScreen(...));
    }

    /**
     * Hook the login screen, once WordPress says we are on one.
     */
    public function hookLoginScreen(): void
    {
        if (! $this->app->make(LoginConfiguration::class)->enabled) {
            return;
        }

        $screen = $this->app->make(LoginScreen::class);
        $action = $this->app->make(Action::class);
        $filter = $this->app->make(Filter::class);

        // Late, so the stylesheet is the last thing in <head> and does not
        // have to out-specify wp-admin/css/login.css by accident.
        $action->add('login_head', $screen->printStyles(...), 20);
        $action->add('login_footer', $screen->printCredit(...));

        $filter->add('login_body_class', $screen->bodyClass(...));
        $filter->add('login_headerurl', $screen->headerUrl(...));
        $filter->add('login_headertext', $screen->headerText(...));
    }

    /**
     * The packaged rules, or nothing if the file cannot be read.
     *
     * A missing stylesheet is not worth a branch of its own: the screen then
     * gets the theme's tokens and no rules, which is what an empty string
     * already produces.
     */
    private function rules(): string
    {
        return (string) @file_get_contents(self::STYLESHEET);
    }

    /**
     * @return array<string>
     */
    public function provides(): array
    {
        return [
            DesignSettingsRepository::class,
            LoginConfiguration::class,
            LoginStylesheet::class,
            LoginScreen::class,
            LogoResolver::class,
        ];
    }
}
