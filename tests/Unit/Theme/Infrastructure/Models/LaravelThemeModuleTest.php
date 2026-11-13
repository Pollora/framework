<?php

declare(strict_types=1);

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Mockery as m;
use Pollora\Hook\Domain\Contract\Action;
use Pollora\Hook\Domain\Contract\HookInterface;
use Pollora\Theme\Infrastructure\Models\LaravelThemeModule;
use Pollora\Theme\Infrastructure\Services\ThemeAutoloader;

function laravelModuleDir(): string
{
    $dir = sys_get_temp_dir().'/pollora-lmodule-'.bin2hex(random_bytes(6));
    mkdir($dir);

    return $dir;
}

function laravelModuleCleanup(string $dir): void
{
    (new Illuminate\Filesystem\Filesystem)->deleteDirectory($dir);
}

describe('LaravelThemeModule paths', function (): void {
    it('derives every directory from the theme path', function (): void {
        $module = new LaravelThemeModule('demo', '/themes/demo', new Container);

        expect($module->getRoutesPath())->toBe('/themes/demo/routes')
            ->and($module->getViewsPath())->toBe('/themes/demo/views')
            ->and($module->getConfigPath())->toBe('/themes/demo/config')
            ->and($module->getMigrationsPath())->toBe('/themes/demo/database/migrations')
            ->and($module->getFactoriesPath())->toBe('/themes/demo/database/factories')
            ->and($module->getSeedersPath())->toBe('/themes/demo/database/seeders')
            ->and($module->getAssetsPath())->toBe('/themes/demo/assets')
            ->and($module->getExtraPath(''))->toBe('/themes/demo')
            ->and($module->getExtraPath('0'))->toBe('/themes/demo')
            ->and($module->getExtraPath('/lang/'))->toBe('/themes/demo/lang');
    });

    it('reads providers, aliases and files from its metadata', function (): void {
        $module = new LaravelThemeModule('demo', '/themes/demo', new Container);

        expect($module->getProviders())->toBe([])->and($module->getAliases())->toBe([])->and($module->getFiles())->toBe([]);

        $module->set('providers', ['A'])->set('aliases', ['Foo' => 'Bar'])->set('files', ['x.php']);

        expect($module->getProviders())->toBe(['A'])
            ->and($module->getAliases())->toBe(['Foo' => 'Bar'])
            ->and($module->getFiles())->toBe(['x.php']);
    });

    it('detects migrations, routes and views on disk', function (): void {
        $dir = laravelModuleDir();
        $module = new LaravelThemeModule('demo', $dir, new Container);

        try {
            $empty = [$module->hasMigrations(), $module->hasRoutes(), $module->hasViews()];

            mkdir($dir.'/database/migrations', 0777, true);
            mkdir($dir.'/routes');
            mkdir($dir.'/views');
            $dirsOnly = [$module->hasMigrations(), $module->hasRoutes(), $module->hasViews()];

            touch($dir.'/database/migrations/2024_create.php');
            touch($dir.'/routes/api.php');
            $filled = [$module->hasMigrations(), $module->hasRoutes(), $module->hasViews()];
        } finally {
            laravelModuleCleanup($dir);
        }

        expect($empty)->toBe([false, false, false])
            ->and($dirsOnly)->toBe([false, false, true])
            ->and($filled)->toBe([true, true, true]);
    });

    it('derives the cached services path from the snake-cased theme name', function (): void {
        $app = m::mock(Container::class);
        $app->shouldReceive('getCachedServicesPath')->andReturn('/bootstrap/cache/services.php');
        $app->shouldReceive('getCachedConfigPath')->andReturn('/bootstrap/cache/config.php');
        $previous = Container::getInstance();
        $global = new Container;
        $global->instance('config', new Repository);
        Container::setInstance($global);

        try {
            $path = (new LaravelThemeModule('MyTheme', '/t', $app))->getCachedServicesPath();
        } finally {
            Container::setInstance($previous);
        }

        expect($path)->toBe('/bootstrap/cache/my_theme_theme.php');
    });
});

describe('LaravelThemeModule registration', function (): void {
    it('lets the autoloader register the theme when it is bound', function (): void {
        $app = new Container;
        $module = new LaravelThemeModule('demo', '/t', $app);
        $autoloader = m::mock(ThemeAutoloader::class);
        $registered = [];
        $autoloader->shouldReceive('registerThemeModule')->andReturnUsing(function ($theme) use (&$registered): void {
            $registered[] = $theme;
        });
        $app->instance(ThemeAutoloader::class, $autoloader);

        $module->registerAutoloading();

        expect($registered)->toBe([$module]);
    });

    it('does nothing without an autoloader', function (): void {
        $module = new LaravelThemeModule('demo', '/t', new Container);

        expect($module->registerAutoloading())->toBeNull();
    });

    it('requires the declared files that exist and ignores the others', function (): void {
        $dir = laravelModuleDir();
        $marker = 'POLLORA_LMODULE_'.strtoupper(bin2hex(random_bytes(4)));
        file_put_contents($dir.'/boot.php', "<?php define('{$marker}', true);");
        $app = new Container;
        $app->instance('config', new Repository);
        $module = new LaravelThemeModule('demo', $dir, $app);
        $module->set('files', ['boot.php', 'missing.php']);

        try {
            $module->register();
        } finally {
            laravelModuleCleanup($dir);
        }

        expect(defined($marker))->toBeTrue();
    });

    it('loads theme config files into the theme.* config keys', function (): void {
        $dir = laravelModuleDir();
        mkdir($dir.'/config');
        file_put_contents($dir.'/config/supports.php', "<?php return ['title-tag'];");
        $app = new Container;
        $app->instance('config', $config = new Repository);

        try {
            (new LaravelThemeModule('demo', $dir, $app))->register();
        } finally {
            laravelModuleCleanup($dir);
        }

        expect($config->get('theme.supports'))->toBe(['title-tag']);
    });

    it('defers translation-dependent config files to the init hook', function (): void {
        $dir = laravelModuleDir();
        mkdir($dir.'/config');
        file_put_contents($dir.'/config/menus.php', "<?php return ['primary' => 'Primary'];");
        $app = new Container;
        $app->instance('config', $config = new Repository);
        $callbacks = [];
        $action = m::mock(Action::class);
        $action->shouldReceive('add')->andReturnUsing(function (string $hook, Closure $callback) use (&$callbacks): HookInterface {
            $callbacks[$hook] = $callback;

            return m::mock(HookInterface::class);
        });
        $app->instance(Action::class, $action);

        try {
            (new LaravelThemeModule('demo', $dir, $app))->register();
            $before = $config->get('theme.menus');
            $callbacks['init']();
            $after = $config->get('theme.menus');
        } finally {
            laravelModuleCleanup($dir);
        }

        expect(array_keys($callbacks))->toBe(['init'])
            ->and($before)->toBeNull()
            ->and($after)->toBe(['primary' => 'Primary']);
    });

    it('keeps translation-dependent configs aside when no hook system is available', function (): void {
        $dir = laravelModuleDir();
        mkdir($dir.'/config');
        file_put_contents($dir.'/config/sidebars.php', '<?php return [];');
        $app = new Container;
        $app->instance('config', $config = new Repository);
        $module = new LaravelThemeModule('demo', $dir, $app);

        try {
            $module->register();
            $delayed = (new ReflectionProperty($module, 'delayedConfigs'))->getValue($module);
        } finally {
            laravelModuleCleanup($dir);
        }

        expect($delayed)->toBe(['theme.sidebars' => $dir.'/config/sidebars.php'])
            ->and($config->has('theme.sidebars'))->toBeFalse();
    });

    it('registers the theme languages under the theme namespace when a translator is bound', function (): void {
        $dir = laravelModuleDir();
        mkdir($dir.'/languages');
        $app = new Container;
        $app->instance('config', new Repository);
        $translator = new class
        {
            public array $namespaces = [];

            public function addNamespace(string $namespace, string $path): void
            {
                $this->namespaces[$namespace] = $path;
            }
        };
        $app->instance('translator', $translator);

        try {
            (new LaravelThemeModule('Demo', $dir, $app))->register();
        } finally {
            laravelModuleCleanup($dir);
        }

        expect($translator->namespaces)->toBe(['demo' => $dir.'/languages']);
    });

    it('skips the translations when the theme has no languages directory', function (): void {
        $dir = laravelModuleDir();
        $app = new Container;
        $app->instance('config', new Repository);
        $translator = new class
        {
            public int $calls = 0;

            public function addNamespace(string $namespace, string $path): void
            {
                $this->calls++;
            }
        };
        $app->instance('translator', $translator);

        try {
            (new LaravelThemeModule('demo', $dir, $app))->register();
        } finally {
            laravelModuleCleanup($dir);
        }

        expect($translator->calls)->toBe(0);
    });
});
