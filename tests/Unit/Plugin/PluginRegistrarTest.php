<?php

declare(strict_types=1);

use Brain\Monkey\Functions;
use Illuminate\Container\Container;
use Illuminate\Foundation\AliasLoader;
use Pollora\Modules\Domain\Contracts\ModuleDiscoveryOrchestratorInterface;
use Pollora\Modules\Domain\Contracts\ModuleRepositoryInterface;
use Pollora\Modules\Infrastructure\Services\ModuleAssetManager;
use Pollora\Modules\Infrastructure\Services\ModuleConfigurationLoader;
use Pollora\Modules\Infrastructure\Services\ModuleRouteLoader;
use Pollora\Plugin\Application\Services\PluginRegistrar;
use Pollora\Plugin\Infrastructure\Models\LaravelPluginModule;
use Pollora\Plugin\Infrastructure\Repositories\PluginRepository;
use Pollora\Plugin\Infrastructure\Services\WordPressPluginParser;
use Psr\Log\LoggerInterface;

if (! defined('WP_PLUGIN_DIR')) {
    define('WP_PLUGIN_DIR', sys_get_temp_dir().'/wp-content/plugins');
}

function removePluginRegistrarTree(string $path): void
{
    if (! is_dir($path)) {
        return;
    }

    foreach (scandir($path) ?: [] as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        is_dir($path.'/'.$item) ? removePluginRegistrarTree($path.'/'.$item) : unlink($path.'/'.$item);
    }

    rmdir($path);
}

/**
 * Create a plugin directory holding a main file with valid headers.
 */
function makeRegistrarPlugin(string $parent, string $name, string $version = '1.4.0'): string
{
    mkdir($parent.'/'.$name.'/app', 0755, true);
    file_put_contents($parent.'/'.$name.'/'.$name.'.php', "<?php\n/*\nPlugin Name: ".ucfirst($name)."\nVersion: {$version}\n*/\n");

    return $parent.'/'.$name;
}

beforeEach(function (): void {
    // LaravelPluginModule::register() asks for the alias loader: keep the global one as it was.
    $this->aliasLoaderProperty = new ReflectionProperty(AliasLoader::class, 'instance');
    $this->previousAliasLoader = $this->aliasLoaderProperty->getValue();

    $this->root = sys_get_temp_dir().'/pollora-plugin-registrar-'.uniqid();
    mkdir($this->root, 0755, true);

    $this->container = new Container;
    $this->errors = [];
    $logger = Mockery::mock(LoggerInterface::class);
    $logger->shouldReceive('error')->andReturnUsing(function (string $message): void {
        $this->errors[] = $message;
    });
    $this->container->instance(LoggerInterface::class, $logger);

    $this->registrar = new PluginRegistrar($this->container, new WordPressPluginParser);
});

afterEach(function (): void {
    $this->aliasLoaderProperty->setValue(null, $this->previousAliasLoader);
    removePluginRegistrarTree($this->root);
});

describe('register()', function (): void {
    it('returns an enabled plugin built from the headers of its main file', function (): void {
        $path = makeRegistrarPlugin($this->root, 'acme', '2.5.0');

        $plugin = $this->registrar->register('acme', $path);

        expect($plugin)->toBeInstanceOf(LaravelPluginModule::class)
            ->and($plugin->getName())->toBe('acme')
            ->and($plugin->getVersion())->toBe('2.5.0')
            ->and($plugin->get('Name'))->toBe('Acme')
            ->and($plugin->isEnabled())->toBeTrue();
    });

    it('remembers the plugin it registered', function (): void {
        $plugin = $this->registrar->register('acme', makeRegistrarPlugin($this->root, 'acme'));

        expect($this->registrar->isPluginRegistered('acme'))->toBeTrue()
            ->and($this->registrar->getRegisteredPlugin('acme'))->toBe($plugin)
            ->and($this->registrar->getRegisteredPlugins())->toBe(['acme' => $plugin])
            ->and($this->registrar->getRegisteredPluginCount())->toBe(1)
            ->and($this->registrar->isPluginRegistered('other'))->toBeFalse()
            ->and($this->registrar->getRegisteredPlugin('other'))->toBeNull();
    });

    it('tolerates a trailing slash on the plugin path', function (): void {
        $plugin = $this->registrar->register('acme', makeRegistrarPlugin($this->root, 'acme', '3.0.0').'/');

        expect($plugin->getVersion())->toBe('3.0.0');
    });

    it('registers several plugins at once, keyed by name', function (): void {
        $plugins = $this->registrar->registerMultiple([
            'acme' => makeRegistrarPlugin($this->root, 'acme'),
            'beta' => makeRegistrarPlugin($this->root, 'beta'),
        ]);

        expect(array_keys($plugins))->toBe(['acme', 'beta'])
            ->and($this->registrar->getRegisteredPluginCount())->toBe(2);
    });
});

describe('wiring into the module services', function (): void {
    it('lets every module service work on the plugin, in the order register() promises', function (): void {
        $path = makeRegistrarPlugin($this->root, 'acme');
        mkdir($path.'/views');
        $calls = [];

        $repository = Mockery::mock(PluginRepository::class);
        $repository->shouldReceive('resetCache')->andReturnUsing(function () use (&$calls): void {
            $calls[] = 'resetCache';
        });
        $this->container->instance(ModuleRepositoryInterface::class, $repository);

        $discovery = Mockery::mock(ModuleDiscoveryOrchestratorInterface::class);
        $discovery->shouldReceive('discover')->andReturnUsing(function (string $directory) use (&$calls): void {
            $calls[] = 'discover:'.basename($directory);
        });
        $this->container->instance(ModuleDiscoveryOrchestratorInterface::class, $discovery);

        $config = Mockery::mock(ModuleConfigurationLoader::class);
        $config->shouldReceive('loadModuleConfiguration')->andReturnUsing(function (string $modulePath, string $type) use (&$calls): void {
            $calls[] = 'config:'.basename($modulePath).':'.$type;
        });
        $this->container->instance(ModuleConfigurationLoader::class, $config);

        $assets = Mockery::mock(ModuleAssetManager::class);
        $assets->shouldReceive('setupModuleAssets')->andReturnUsing(function (string $name, string $modulePath, string $type) use (&$calls): void {
            $calls[] = "assets:{$name}:{$type}";
        });
        $assets->shouldReceive('loadModuleIncludes')->andReturnUsing(function () use (&$calls): void {
            $calls[] = 'includes';
        });
        $assets->shouldReceive('registerModuleBladeDirectives')->andReturnUsing(function () use (&$calls): void {
            $calls[] = 'blade';
        });
        $this->container->instance(ModuleAssetManager::class, $assets);

        $routes = Mockery::mock(ModuleRouteLoader::class);
        $routes->shouldReceive('loadModuleRoutes')->andReturnUsing(function ($module) use (&$calls): void {
            $calls[] = 'routes:'.$module->getName();
        });
        $this->container->instance(ModuleRouteLoader::class, $routes);

        $this->registrar->register('acme', $path);

        expect($calls)->toBe([
            'resetCache',
            'discover:app',
            'config:acme:plugin',
            'assets:acme:plugin',
            'includes',
            'blade',
            'routes:acme',
        ]);
    });

    it('does not register Blade directives for a plugin without views', function (): void {
        $assets = Mockery::mock(ModuleAssetManager::class);
        $assets->shouldReceive('setupModuleAssets')->once();
        $assets->shouldReceive('loadModuleIncludes')->once();
        $assets->shouldNotReceive('registerModuleBladeDirectives');
        $this->container->instance(ModuleAssetManager::class, $assets);

        $this->registrar->register('acme', makeRegistrarPlugin($this->root, 'acme'));
    });

    it('only resets the cache of a plugin repository', function (): void {
        $repository = Mockery::mock(ModuleRepositoryInterface::class);
        $repository->shouldNotReceive('resetCache');

        $this->container->instance(ModuleRepositoryInterface::class, $repository);

        $this->registrar->register('acme', makeRegistrarPlugin($this->root, 'acme'));

        expect($this->errors)->toBe([]);
    });

    it('prefers app, then src, as the directory to discover', function (string $directory): void {
        $path = $this->root.'/acme';
        mkdir($path.'/'.$directory, 0755, true);
        mkdir($path.'/other');
        file_put_contents($path.'/acme.php', "<?php\n/*\nPlugin Name: Acme\n*/");

        $discovered = [];
        $discovery = Mockery::mock(ModuleDiscoveryOrchestratorInterface::class);
        $discovery->shouldReceive('discover')->andReturnUsing(function (string $directory) use (&$discovered): void {
            $discovered[] = basename($directory);
        });
        $this->container->instance(ModuleDiscoveryOrchestratorInterface::class, $discovery);

        $this->registrar->register('acme', $path);

        expect($discovered)->toBe([$directory]);
    })->with(['app', 'src']);

    it('scans the top-level directories of a plugin without app or src, minus build and vendor ones', function (): void {
        $path = $this->root.'/acme';
        foreach (['includes', 'classes', 'vendor', 'node_modules', 'assets', 'languages', '.git', 'dist'] as $directory) {
            mkdir($path.'/'.$directory, 0755, true);
        }

        file_put_contents($path.'/acme.php', "<?php\n/*\nPlugin Name: Acme\n*/");
        file_put_contents($path.'/readme.txt', 'not a directory');

        $discovered = [];
        $discovery = Mockery::mock(ModuleDiscoveryOrchestratorInterface::class);
        $discovery->shouldReceive('discover')->andReturnUsing(function (string $directory) use (&$discovered): void {
            $discovered[] = basename($directory);
        });
        $this->container->instance(ModuleDiscoveryOrchestratorInterface::class, $discovery);

        $this->registrar->register('acme', $path);

        sort($discovered);
        expect($discovered)->toBe(['classes', 'includes']);
    });
});

describe('failures in a module service', function (): void {
    it('is logged and does not stop the plugin from registering', function (): void {
        $path = makeRegistrarPlugin($this->root, 'acme');

        $discovery = Mockery::mock(ModuleDiscoveryOrchestratorInterface::class);
        $discovery->shouldReceive('discover')->andThrow(new RuntimeException('scan failed'));
        $this->container->instance(ModuleDiscoveryOrchestratorInterface::class, $discovery);

        $config = Mockery::mock(ModuleConfigurationLoader::class);
        $config->shouldReceive('loadModuleConfiguration')->andThrow(new RuntimeException('config failed'));
        $this->container->instance(ModuleConfigurationLoader::class, $config);

        $assets = Mockery::mock(ModuleAssetManager::class);
        $assets->shouldReceive('setupModuleAssets')->andThrow(new RuntimeException('assets failed'));
        $this->container->instance(ModuleAssetManager::class, $assets);

        $routes = Mockery::mock(ModuleRouteLoader::class);
        $routes->shouldReceive('loadModuleRoutes')->andThrow(new RuntimeException('routes failed'));
        $this->container->instance(ModuleRouteLoader::class, $routes);

        $repository = Mockery::mock(PluginRepository::class);
        $repository->shouldReceive('resetCache')->andThrow(new RuntimeException('cache failed'));
        $this->container->instance(ModuleRepositoryInterface::class, $repository);

        $plugin = $this->registrar->register('acme', $path);

        expect($plugin->isEnabled())->toBeTrue()
            ->and($this->errors)->toBe([
                'Failed to invalidate plugin repository cache: cache failed',
                'Plugin discovery error for acme: scan failed',
                'Failed to load plugin configuration: config failed',
                'Failed to setup plugin assets: assets failed',
                'Failed to load plugin routes: routes failed',
            ]);
    });

    it('does not fail either when nobody can log', function (): void {
        $container = new Container;
        $discovery = Mockery::mock(ModuleDiscoveryOrchestratorInterface::class);
        $discovery->shouldReceive('discover')->andThrow(new RuntimeException('scan failed'));
        $container->instance(ModuleDiscoveryOrchestratorInterface::class, $discovery);

        $registrar = new PluginRegistrar($container, new WordPressPluginParser);

        expect($registrar->register('acme', makeRegistrarPlugin($this->root, 'acme'))->isEnabled())->toBeTrue();
    });
});

describe('unregistering', function (): void {
    it('disables and forgets a plugin', function (): void {
        $plugin = $this->registrar->register('acme', makeRegistrarPlugin($this->root, 'acme'));

        expect($this->registrar->unregister('acme'))->toBeTrue()
            ->and($plugin->isEnabled())->toBeFalse()
            ->and($this->registrar->isPluginRegistered('acme'))->toBeFalse();
    });

    it('reports that an unknown plugin was not registered', function (): void {
        expect($this->registrar->unregister('ghost'))->toBeFalse();
    });

    it('disables and forgets every plugin on reset', function (): void {
        $acme = $this->registrar->register('acme', makeRegistrarPlugin($this->root, 'acme'));
        $beta = $this->registrar->register('beta', makeRegistrarPlugin($this->root, 'beta'));

        $this->registrar->resetRegisteredPlugins();

        expect($acme->isEnabled())->toBeFalse()
            ->and($beta->isEnabled())->toBeFalse()
            ->and($this->registrar->getRegisteredPlugins())->toBe([]);
    });

    it('resets the plugin repository cache on reset', function (): void {
        $repository = Mockery::mock(PluginRepository::class);
        $repository->shouldReceive('resetCache')->once();
        $this->container->instance(ModuleRepositoryInterface::class, $repository);

        $this->registrar->resetRegisteredPlugins();
    });
});

describe('by status', function (): void {
    it('filters the registered plugins on their state', function (): void {
        $acme = $this->registrar->register('acme', makeRegistrarPlugin($this->root, 'acme'));
        $beta = $this->registrar->register('beta', makeRegistrarPlugin($this->root, 'beta'));
        $acme->setActive(true);
        $beta->setActive(false)->setEnabled(false);

        expect(array_keys($this->registrar->getRegisteredPluginsByStatus('active')))->toBe(['acme'])
            ->and(array_keys($this->registrar->getRegisteredPluginsByStatus('inactive')))->toBe(['beta'])
            ->and(array_keys($this->registrar->getRegisteredPluginsByStatus('enabled')))->toBe(['acme'])
            ->and(array_keys($this->registrar->getRegisteredPluginsByStatus('disabled')))->toBe(['beta'])
            ->and(array_keys($this->registrar->getRegisteredPluginsByStatus('anything')))->toBe(['acme', 'beta']);
    });
});

describe('registerActivePlugins()', function (): void {
    it('skips the active plugins that have no directory, and the single-file ones', function (): void {
        // WP_PLUGIN_DIR is a constant another test may already have fixed: only unknown directories are used.
        Functions\when('get_option')->justReturn(['ghost-'.uniqid().'/ghost.php', 'hello.php']);

        expect($this->registrar->registerActivePlugins())->toBe([]);
    });

    it('registers nothing when no plugin is active', function (): void {
        Functions\when('get_option')->justReturn([]);

        expect($this->registrar->registerActivePlugins())->toBe([]);
    });
});
