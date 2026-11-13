<?php

declare(strict_types=1);

use Brain\Monkey\Functions;
use Illuminate\Contracts\Translation\Loader;
use Illuminate\Foundation\Application;
use Mockery as m;
use Pollora\Application\Application\Services\ConsoleDetectionService;
use Pollora\Collection\Domain\Contracts\CollectionFactoryInterface;
use Pollora\Config\Domain\Contracts\ConfigRepositoryInterface;
use Pollora\Hook\Domain\Contract\Action;
use Pollora\Hook\Domain\Contract\Filter;
use Pollora\Hook\Domain\Contract\HookInterface;
use Pollora\Theme\Application\Services\ThemeManager;
use Pollora\Theme\Application\Services\ThemeRegistrar;
use Pollora\Theme\Domain\Contracts\ContainerInterface;
use Pollora\Theme\Domain\Contracts\ThemeJsonResolverInterface;
use Pollora\Theme\Domain\Contracts\ThemeRegistrarInterface;
use Pollora\Theme\Domain\Contracts\ThemeService;
use Pollora\Theme\Domain\Contracts\WordPressThemeInterface;
use Pollora\Theme\Domain\Support\ThemeCollection;
use Pollora\Theme\Domain\Support\ThemeConfig;
use Pollora\Theme\Infrastructure\Adapters\DomainContainerAdapter;
use Pollora\Theme\Infrastructure\Providers\ThemeServiceProvider;
use Pollora\Theme\Infrastructure\Repositories\ThemeRepository;
use Pollora\Theme\Infrastructure\Services\EditorStyleResolver;
use Pollora\Theme\Infrastructure\Services\WordPressThemeAdapter;
use Pollora\Theme\UI\Console\Commands\ThemeActivateCommand;
use Pollora\Theme\UI\Console\Commands\ThemeStatusCommand;
use Pollora\Theme\UI\Console\MakeThemeCommand;
use Pollora\Theme\UI\Console\RemoveThemeCommand;
use Pollora\Theme\UI\Http\MissingThemeNotice;

if (! defined('WP_CONTENT_DIR')) {
    $contentDir = sys_get_temp_dir().'/pollora-content-'.uniqid();
    mkdir($contentDir.'/themes', 0755, true);
    define('WP_CONTENT_DIR', $contentDir);
}

function themeProviderHook(): HookInterface
{
    return m::mock(HookInterface::class);
}

function themeProviderCall(object $provider, string $method, mixed ...$args): mixed
{
    return (new ReflectionMethod($provider, $method))->invoke($provider, ...$args);
}

describe('ThemeServiceProvider registration', function (): void {
    beforeEach(function (): void {
        $this->app->instance(Filter::class, m::mock(Filter::class)->shouldIgnoreMissing());
        $this->app->instance(Action::class, m::mock(Action::class)->shouldIgnoreMissing());
        $this->app->register(ThemeServiceProvider::class);
    });

    afterEach(function (): void {
        ThemeConfig::reset();
        (new ReflectionProperty(ThemeCollection::class, 'collectionFactory'))->setValue(null, null);
    });

    it('binds the registrar, the container port and the theme manager', function (): void {
        $this->app->instance(ConsoleDetectionService::class, m::mock(ConsoleDetectionService::class));
        $this->app->instance('translator', new class
        {
            public function getLoader(): Loader
            {
                return m::mock(Loader::class);
            }
        });
        expect($this->app->make(ThemeRegistrarInterface::class))->toBeInstanceOf(ThemeRegistrar::class)
            ->and($this->app->make(ThemeRegistrarInterface::class))->toBe($this->app->make(ThemeRegistrarInterface::class))
            ->and($this->app->make(ContainerInterface::class))->toBeInstanceOf(DomainContainerAdapter::class)
            ->and($this->app->make(ThemeService::class))->toBeInstanceOf(ThemeManager::class)
            ->and($this->app->make('theme'))->toBe($this->app->make(ThemeService::class))
            ->and($this->app->make(WordPressThemeInterface::class))->toBeInstanceOf(WordPressThemeAdapter::class);
    });

    it('binds the theme repository with its parser and collection factory', function (): void {
        expect($this->app->make('theme.repository'))->toBeInstanceOf(ThemeRepository::class);
    });

    it('resolves the JSON and editor style resolvers against the public path', function (): void {
        expect($this->app->make(ThemeJsonResolverInterface::class))->not->toBeNull()
            ->and($this->app->make(EditorStyleResolver::class))->toBeInstanceOf(EditorStyleResolver::class);
    });

    it('registers the theme commands', function (): void {
        expect($this->app->make('theme.generator'))->toBeInstanceOf(MakeThemeCommand::class)
            ->and($this->app->make('theme.remover'))->toBeInstanceOf(RemoveThemeCommand::class)
            ->and($this->app->make('theme.status'))->toBeInstanceOf(ThemeStatusCommand::class)
            ->and($this->app->make('theme.activator'))->toBeInstanceOf(ThemeActivateCommand::class);
    });

    it('keeps the old LaravelThemeModule class names working', function (): void {
        expect(class_exists('Pollora\\Modules\\Domain\\Models\\LaravelThemeModule'))->toBeTrue()
            ->and(class_exists('Pollora\\Theme\\Domain\\Models\\LaravelThemeModule'))->toBeTrue();
    });

    it('merges the default theme configuration', function (): void {
        expect($this->app['config']->get('theme.assets_path'))->toBe('resources/assets')
            ->and($this->app['config']->get('theme.containerDir.view'))->toBe('resources/views')
            ->and($this->app['config']->get('theme.path'))->toBe(base_path('themes'));
    });

    it('hands the config repository and the collection factory to the theme utility classes', function (): void {
        expect(ThemeConfig::isInitialized())->toBeTrue()
            ->and($this->app->make(CollectionFactoryInterface::class))->not->toBeNull()
            ->and(ThemeCollection::make([1, 2]))->not->toBeNull()
            ->and($this->app->make(ConfigRepositoryInterface::class))->not->toBeNull();
    });
});

describe('ThemeServiceProvider theme roots', function (): void {
    beforeEach(function (): void {
        $this->provider = new ThemeServiceProvider($this->app);
        $this->themes = base_path('themes');
    });

    it('points any foreign theme root back to the Pollora themes directory', function (): void {
        expect(themeProviderCall($this->provider, 'fixThemeRoot', '/somewhere/else'))->toBe($this->themes)
            ->and(themeProviderCall($this->provider, 'fixThemeRoot', $this->themes))->toBe($this->themes);
    });

    it('keeps the WordPress content themes root as it is', function (): void {
        expect(themeProviderCall($this->provider, 'fixThemeRoot', '/srv/wp/content/themes/'))->toBe('/srv/wp/content/themes/');
    });

    it('leaves an empty theme_roots transient alone', function (): void {
        expect(themeProviderCall($this->provider, 'handleThemeRootsTransient', false))->toBeFalse()
            ->and(themeProviderCall($this->provider, 'handleThemeRootsTransient', []))->toBe([]);
    });

    it('rewrites the theme_roots transient and stores it back when it changed', function (): void {
        Functions\expect('set_site_transient')->once()->with('theme_roots', [
            'core' => WP_CONTENT_DIR.'/themes',
            'mine' => $this->themes,
        ]);

        $result = themeProviderCall($this->provider, 'handleThemeRootsTransient', [
            'core' => '/srv/wp/content/themes/',
            'mine' => '/old/place',
        ]);

        expect($result)->toBe(['core' => WP_CONTENT_DIR.'/themes', 'mine' => $this->themes]);
    });

    it('does not store the transient again when it is already normalized', function (): void {
        Functions\expect('set_site_transient')->never();
        $roots = ['mine' => $this->themes];

        expect(themeProviderCall($this->provider, 'handleThemeRootsTransient', $roots))->toBe($roots);
    });

    it('only treats existing directories as valid theme directories', function (): void {
        expect(themeProviderCall($this->provider, 'isValidThemeDirectory', sys_get_temp_dir()))->toBeTrue()
            ->and(themeProviderCall($this->provider, 'isValidThemeDirectory', '/does/not/exist-'.bin2hex(random_bytes(4))))->toBeFalse()
            ->and(themeProviderCall($this->provider, 'isValidThemeDirectory', null))->toBeFalse();
    });
});

describe('ThemeServiceProvider boot', function (): void {
    it('hooks the theme root, update and theme.json filters and the editor styles action', function (): void {
        $hooks = [];
        $filter = m::mock(Filter::class);
        $filter->shouldReceive('add')->andReturnUsing(function (string $hook) use (&$hooks): HookInterface {
            $hooks[] = $hook;

            return themeProviderHook();
        });
        $actionHooks = [];
        $action = m::mock(Action::class);
        $action->shouldReceive('add')->andReturnUsing(function (string $hook, mixed $cb, int $priority = 10) use (&$actionHooks): HookInterface {
            $actionHooks[] = [$hook, $priority];

            return themeProviderHook();
        });
        $this->app->instance(Action::class, $action);

        (new ThemeServiceProvider($this->app))->boot($filter);

        expect($hooks)->toBe([
            'theme_root',
            'site_transient_theme_roots',
            'wp_theme_json_data_theme',
            'site_transient_update_themes',
        ])->and($actionHooks)->toBe([['admin_notices', 10], ['after_setup_theme', 20]]);
    });

    it('registers the missing theme notice renderer on admin_notices', function (): void {
        $renderers = [];
        $action = m::mock(Action::class);
        $action->shouldReceive('add')->andReturnUsing(function (string $hook, mixed $cb) use (&$renderers): HookInterface {
            if ($hook === 'admin_notices') {
                $renderers[] = $cb;
            }

            return themeProviderHook();
        });
        $this->app->instance(Action::class, $action);
        $filter = m::mock(Filter::class);
        $filter->shouldReceive('add')->andReturn(themeProviderHook());

        (new ThemeServiceProvider($this->app))->boot($filter);

        expect($renderers)->toHaveCount(1)
            ->and($renderers[0][0])->toBeInstanceOf(MissingThemeNotice::class)
            ->and($renderers[0][1])->toBe('render');
    });
});

describe('ThemeServiceProvider editor styles', function (): void {
    function themeProviderEditorCallback(Application $app, ?EditorStyleResolver $resolver = null): Closure
    {
        $captured = null;
        $action = m::mock(Action::class);
        $action->shouldReceive('add')->with('after_setup_theme', m::type(Closure::class), 20)
            ->andReturnUsing(function (string $hook, Closure $cb) use (&$captured): HookInterface {
                $captured = $cb;

                return themeProviderHook();
            });
        $app->instance(Action::class, $action);
        if ($resolver instanceof EditorStyleResolver) {
            $app->instance(EditorStyleResolver::class, $resolver);
        }
        themeProviderCall(new ThemeServiceProvider($app), 'registerEditorStyles');

        return $captured;
    }

    it('adds nothing when the theme does not support editor styles', function (): void {
        Functions\when('current_theme_supports')->justReturn(false);
        $added = [];
        Functions\when('add_editor_style')->alias(function (string $url) use (&$added): void {
            $added[] = $url;
        });
        $callback = themeProviderEditorCallback($this->app, m::mock(EditorStyleResolver::class));

        $callback();

        expect($added)->toBe([]);
    });

    it('adds each resolved style, as an absolute URL, when editor styles are supported', function (): void {
        Functions\when('current_theme_supports')->alias(fn (string $feature): bool => $feature === 'editor-styles');
        Functions\when('get_stylesheet')->justReturn('demo');
        Functions\when('home_url')->alias(fn (string $path): string => 'https://site.test'.$path);
        $resolver = m::mock(EditorStyleResolver::class);
        $resolver->shouldReceive('resolve')->with('demo')->andReturn(['build/a.css', 'build/b.css']);
        $added = [];
        Functions\when('add_editor_style')->alias(function (string $url) use (&$added): void {
            $added[] = $url;
        });
        $callback = themeProviderEditorCallback($this->app, $resolver);

        $callback();

        expect($added)->toBe(['https://site.test/build/a.css', 'https://site.test/build/b.css']);
    });
});

describe('ThemeServiceProvider theme.json filter', function (): void {
    function themeProviderThemeJsonCallback(Application $app, ?ThemeJsonResolverInterface $resolver): Closure
    {
        $captured = null;
        $filter = m::mock(Filter::class);
        $filter->shouldReceive('add')->with('wp_theme_json_data_theme', m::type(Closure::class))
            ->andReturnUsing(function (string $hook, Closure $cb) use (&$captured): HookInterface {
                $captured = $cb;

                return themeProviderHook();
            });
        $app->instance(ThemeJsonResolverInterface::class, $resolver);
        $provider = new ThemeServiceProvider($app);
        (new ReflectionProperty($provider, 'filter'))->setValue($provider, $filter);
        themeProviderCall($provider, 'registerThemeJsonFilter');

        return $captured;
    }

    it('keeps the WordPress theme.json data when the theme has no built data', function (): void {
        Functions\when('get_stylesheet')->justReturn('demo');
        $resolver = m::mock(ThemeJsonResolverInterface::class);
        $resolver->shouldReceive('resolve')->with('demo')->andReturnNull();
        $callback = themeProviderThemeJsonCallback($this->app, $resolver);
        $original = new stdClass;

        expect($callback($original))->toBe($original);
    });
});
