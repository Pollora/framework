<?php

declare(strict_types=1);

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Foundation\AliasLoader;
use Illuminate\Foundation\Application;
use Pollora\Hook\Domain\Contract\Action;
use Pollora\Hook\Domain\Contract\HookInterface;
use Pollora\Modules\Infrastructure\Services\ModuleAutoloader;
use Pollora\Plugin\Infrastructure\Models\LaravelPluginModule;
use Pollora\Plugin\Infrastructure\Services\PluginAutoloader;

beforeEach(function (): void {
    $this->previousContainer = Container::getInstance();
    $this->previousAliasLoader = AliasLoader::getInstance();

    // A loader of its own (never put on the autoload stack), so the aliases do not outlive the test.
    $loader = (new ReflectionClass(AliasLoader::class))->newInstanceWithoutConstructor();
    (new ReflectionProperty($loader, 'aliases'))->setValue($loader, []);
    AliasLoader::setInstance($loader);

    $this->root = sys_get_temp_dir().'/pollora-laravel-plugin-'.uniqid();
    mkdir($this->root.'/acme', 0755, true);

    // Building an Application replaces the global container; afterEach puts the previous one back.
    $this->app = new Application($this->root);
    $this->app->instance('config', new Repository);

    $this->module = new LaravelPluginModule('acme', $this->root.'/acme', $this->app);
});

afterEach(function (): void {
    Container::setInstance($this->previousContainer);
    AliasLoader::setInstance($this->previousAliasLoader);
    removePluginModuleTree($this->root);
});

function removePluginModuleTree(string $path): void
{
    if (! is_dir($path)) {
        return;
    }

    foreach (scandir($path) ?: [] as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        is_dir($path.'/'.$item) ? removePluginModuleTree($path.'/'.$item) : unlink($path.'/'.$item);
    }

    rmdir($path);
}

describe('paths', function (): void {
    it('places each kind of resource in its conventional subdirectory', function (): void {
        $base = $this->root.'/acme';

        expect($this->module->getRoutesPath())->toBe($base.'/routes')
            ->and($this->module->getViewsPath())->toBe($base.'/views')
            ->and($this->module->getConfigPath())->toBe($base.'/config')
            ->and($this->module->getMigrationsPath())->toBe($base.'/database/migrations')
            ->and($this->module->getFactoriesPath())->toBe($base.'/database/factories')
            ->and($this->module->getSeedersPath())->toBe($base.'/database/seeders')
            ->and($this->module->getAssetsPath())->toBe($base.'/assets')
            ->and($this->module->getAdminPath())->toBe($base.'/admin')
            ->and($this->module->getPublicPath())->toBe($base.'/public')
            ->and($this->module->getIncludesPath())->toBe($base.'/includes');
    });

    it('joins an extra path with exactly one slash', function (): void {
        $base = $this->root.'/acme';

        expect($this->module->getExtraPath('assets/js'))->toBe($base.'/assets/js')
            ->and($this->module->getExtraPath('/assets/js/'))->toBe($base.'/assets/js')
            ->and($this->module->getExtraPath(''))->toBe($base);
    });

    it('names its cached services file after the plugin', function (): void {
        $this->app->useStoragePath($this->root.'/storage');

        expect($this->module->getCachedServicesPath())->toEndWith('/cache/acme_plugin.php');
    });
});

describe('what the plugin ships', function (): void {
    it('detects migrations only when a migration file exists', function (): void {
        expect($this->module->hasMigrations())->toBeFalse();

        mkdir($this->root.'/acme/database/migrations', 0755, true);
        expect($this->module->hasMigrations())->toBeFalse();

        touch($this->root.'/acme/database/migrations/2024_01_01_create_things.php');
        expect($this->module->hasMigrations())->toBeTrue();
    });

    it('detects routes from any of the web, api or admin files', function (string $file): void {
        expect($this->module->hasRoutes())->toBeFalse();

        mkdir($this->root.'/acme/routes');
        expect($this->module->hasRoutes())->toBeFalse();

        touch($this->root.'/acme/routes/'.$file);
        expect($this->module->hasRoutes())->toBeTrue();
    })->with(['web.php', 'api.php', 'admin.php']);

    it('does not count an unrelated routes file', function (): void {
        mkdir($this->root.'/acme/routes');
        touch($this->root.'/acme/routes/console.php');

        expect($this->module->hasRoutes())->toBeFalse();
    });

    it('detects views, admin and public directories', function (): void {
        expect($this->module->hasViews())->toBeFalse()
            ->and($this->module->hasAdmin())->toBeFalse()
            ->and($this->module->hasPublic())->toBeFalse();

        mkdir($this->root.'/acme/views');
        mkdir($this->root.'/acme/admin');
        mkdir($this->root.'/acme/public');

        expect($this->module->hasViews())->toBeTrue()
            ->and($this->module->hasAdmin())->toBeTrue()
            ->and($this->module->hasPublic())->toBeTrue();
    });

    it('reads providers, aliases and files from its metadata', function (): void {
        $this->module->set('providers', ['Acme\\Provider']);
        $this->module->set('aliases', ['Alias' => 'Acme\\Facade']);
        $this->module->set('files', ['helpers.php']);

        expect($this->module->getProviders())->toBe(['Acme\\Provider'])
            ->and($this->module->getAliases())->toBe(['Alias' => 'Acme\\Facade'])
            ->and($this->module->getFiles())->toBe(['helpers.php']);
    });

    it('has no providers, aliases or files by default', function (): void {
        expect($this->module->getProviders())->toBe([])
            ->and($this->module->getAliases())->toBe([])
            ->and($this->module->getFiles())->toBe([]);
    });
});

describe('register()', function (): void {
    it('registers the aliases the plugin declares', function (): void {
        $this->module->set('aliases', ['AcmeAlias' => stdClass::class]);

        $this->module->register();

        expect(AliasLoader::getInstance()->getAliases())->toBe(['AcmeAlias' => stdClass::class]);
    });

    it('loads the declared files that exist and skips those that do not', function (): void {
        $marker = 'POLLORA_PLUGIN_TEST_'.strtoupper(uniqid());
        file_put_contents($this->root.'/acme/helpers.php', "<?php define('{$marker}', 'loaded');");
        $this->module->set('files', ['missing.php', 'helpers.php']);

        $this->module->register();

        expect(defined($marker))->toBeTrue()
            ->and(constant($marker))->toBe('loaded');
    });

    it('adds the languages directory as a translation namespace of the plugin', function (): void {
        mkdir($this->root.'/acme/languages');
        $translator = Mockery::mock();
        $translator->shouldReceive('addNamespace')->once()->with('acme', $this->root.'/acme/languages');
        $this->app->instance('translator', $translator);

        $this->module->register();
    });

    it('leaves translations alone when there is no languages directory', function (): void {
        $translator = Mockery::mock();
        $translator->shouldNotReceive('addNamespace');
        $this->app->instance('translator', $translator);

        $this->module->register();
    });

    it('does nothing for configuration when the plugin has no config directory', function (): void {
        $this->module->register();

        expect($this->app['config']->all())->toBe([]);
    });

    it('loads each config file under plugin.<name>', function (): void {
        mkdir($this->root.'/acme/config');
        file_put_contents($this->root.'/acme/config/limits.php', '<?php return ["max" => 5];');
        file_put_contents($this->root.'/acme/config/features.php', '<?php return ["beta" => true];');

        $this->module->register();

        expect($this->app['config']->get('plugin.limits'))->toBe(['max' => 5])
            ->and($this->app['config']->get('plugin.features'))->toBe(['beta' => true]);
    });

    it('postpones the config files that depend on translations until WordPress init', function (): void {
        mkdir($this->root.'/acme/config');
        file_put_contents($this->root.'/acme/config/settings.php', '<?php return ["title" => "Hello"];');
        file_put_contents($this->root.'/acme/config/limits.php', '<?php return ["max" => 5];');

        $init = null;
        $action = Mockery::mock(Action::class);
        $action->shouldReceive('add')->once()->andReturnUsing(function (string $hook, callable $callback) use (&$init): HookInterface {
            expect($hook)->toBe('init');
            $init = $callback;

            return Mockery::mock(HookInterface::class);
        });
        $this->app->instance(Action::class, $action);

        $this->module->register();

        expect($this->app['config']->get('plugin.limits'))->toBe(['max' => 5])
            ->and($this->app['config']->has('plugin.settings'))->toBeFalse();

        $init();

        expect($this->app['config']->get('plugin.settings'))->toBe(['title' => 'Hello']);
    });

    it('remembers a postponed config file when the hook service is not available yet', function (): void {
        mkdir($this->root.'/acme/config');
        file_put_contents($this->root.'/acme/config/admin-menu.php', '<?php return ["slug" => "acme"];');

        $this->module->register();

        expect($this->app['config']->has('plugin.admin-menu'))->toBeFalse()
            ->and((new ReflectionProperty($this->module, 'delayedConfigs'))->getValue($this->module))
            ->toBe(['plugin.admin-menu' => $this->root.'/acme/config/admin-menu.php']);
    });
});

describe('registerAutoloading()', function (): void {
    it('hands the plugin to the autoloader when one is bound', function (): void {
        mkdir($this->root.'/acme/app');
        $autoloader = new PluginAutoloader($this->app);
        $this->app->instance(PluginAutoloader::class, $autoloader);

        try {
            $this->module->registerAutoloading();

            expect($autoloader->getRegisteredNamespaces())->toBe(['Plugin\\Acme\\' => $this->root.'/acme/app']);
        } finally {
            $this->app->make(ModuleAutoloader::CLASS_LOADER)->unregister();
        }
    });

    it('does nothing when no autoloader is bound', function (): void {
        $this->module->registerAutoloading();

        expect($this->app->bound(PluginAutoloader::class))->toBeFalse();
    });
});
