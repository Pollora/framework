<?php

declare(strict_types=1);

use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use Illuminate\Container\Container;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Foundation\Application;
use Pollora\Application\Application\Services\ConsoleDetectionService;
use Pollora\Modules\Domain\Contracts\ModuleRepositoryInterface;
use Pollora\Plugin\Application\Services\PluginManager;
use Pollora\Plugin\Domain\Exceptions\PluginException;
use Pollora\Plugin\Domain\Models\PluginModule;
use Pollora\Plugin\Domain\Support\PluginCollection;

/**
 * A manager that reads its plugins from a directory of the test, whatever WP_PLUGIN_DIR says.
 */
function pluginManagerOn(string $pluginsPath, ?ModuleRepositoryInterface $repository = null, bool $console = true, ?Translator $translator = null): PluginManager
{
    $detection = Mockery::mock(ConsoleDetectionService::class);
    $detection->shouldReceive('isConsole')->andReturn($console);

    return new class(new Container, $translator, $repository, $detection, $pluginsPath) extends PluginManager
    {
        public function __construct($app, $translator, $repository, $detection, private readonly string $pluginsPath)
        {
            parent::__construct($app, $translator, $repository, $detection);
        }

        protected function getPluginsPath(): string
        {
            return $this->pluginsPath;
        }
    };
}

function pluginManagerModule(string $name, array $headers = [], bool $active = false): PluginModule
{
    $module = new PluginModule($name, '/plugins/'.$name);
    $module->setHeaders($headers);
    $module->setActive($active);

    return $module;
}

function pluginManagerRepository(PluginModule ...$modules): ModuleRepositoryInterface
{
    $repository = Mockery::mock(ModuleRepositoryInterface::class);
    $byName = [];
    foreach ($modules as $module) {
        $byName[$module->getName()] = $module;
    }
    $repository->shouldReceive('all')->andReturn($byName);
    $repository->shouldReceive('find')->andReturnUsing(fn (string $name) => $byName[$name] ?? null);

    return $repository;
}

function removePluginManagerTree(string $path): void
{
    if (! is_dir($path)) {
        return;
    }

    foreach (scandir($path) ?: [] as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        is_dir($path.'/'.$item) ? removePluginManagerTree($path.'/'.$item) : unlink($path.'/'.$item);
    }

    rmdir($path);
}

beforeEach(function (): void {
    $this->root = sys_get_temp_dir().'/pollora-plugin-manager-'.uniqid();
    mkdir($this->root, 0755, true);
});

afterEach(function (): void {
    removePluginManagerTree($this->root);
});

describe('load()', function (): void {
    it('remembers the metadata of the plugin it loaded', function (): void {
        mkdir($this->root.'/acme');
        $manager = pluginManagerOn($this->root);

        $manager->load('acme');

        expect($manager->plugin()?->getName())->toBe('acme')
            ->and($manager->plugin()?->getBasePath())->toBe($this->root.'/acme');
    });

    it('reads the plugin.json of the plugin', function (): void {
        mkdir($this->root.'/acme');
        file_put_contents($this->root.'/acme/plugin.json', '{"providers": ["Acme\\\\Provider"]}');
        $manager = pluginManagerOn($this->root);

        $manager->load('acme');

        expect($manager->plugin()?->getConfig())->toBe(['providers' => ['Acme\\Provider']]);
    });

    it('refuses a plugin without a directory outside the console', function (): void {
        $manager = pluginManagerOn($this->root, console: false);

        expect(fn () => $manager->load('ghost'))->toThrow(PluginException::class, 'Plugin directory ghost not found.');
    });

    it('lets the console load a plugin that has no directory yet', function (): void {
        $manager = pluginManagerOn($this->root, console: true);

        $manager->load('fresh');

        expect($manager->plugin()?->getName())->toBe('fresh');
    });

    it('registers the languages directory of the plugin under its name', function (): void {
        mkdir($this->root.'/acme');
        $translator = Mockery::mock(Translator::class);
        $translator->shouldReceive('addNamespace')->once()->with('acme', $this->root.'/acme/languages');

        pluginManagerOn($this->root, translator: $translator)->load('acme');
    });
});

describe('getAvailablePlugins()', function (): void {
    it('lists the directories that hold a main file named after them', function (): void {
        mkdir($this->root.'/with-main');
        touch($this->root.'/with-main/with-main.php');
        mkdir($this->root.'/without-main');
        touch($this->root.'/without-main/other.php');
        touch($this->root.'/loose-file.php');

        expect(array_values(pluginManagerOn($this->root)->getAvailablePlugins()))->toBe(['with-main']);
    });

    it('is empty when the plugins directory does not exist', function (): void {
        expect(pluginManagerOn($this->root.'/missing')->getAvailablePlugins())->toBe([]);
    });
});

describe('paths', function (): void {
    it('builds a plugin path from the plugins directory', function (): void {
        expect(pluginManagerOn('/wp/plugins')->path('acme', '/config/app.php'))->toBe('/wp/plugins/acme/config/app.php');
    });

    it('moves the studly plugin name into the second segment of an app path', function (): void {
        $previous = Container::getInstance();
        $app = new Application($this->root);

        try {
            $manager = pluginManagerOn('/wp/plugins');

            expect($manager->getPluginAppPath('my-plugin', 'Http/Controllers'))->toBe($app->path('Http/MyPlugin/Controllers'))
                ->and($manager->getPluginAppPath('my-plugin', 'Models'))->toBe($app->path('Models'))
                ->and($manager->getPluginAppPath('my-plugin'))->toBe($app->path());
        } finally {
            Container::setInstance($previous);
        }
    });
});

describe('active()', function (): void {
    it('is the first active plugin of WordPress', function (): void {
        Functions\when('get_option')->justReturn(['acme/acme.php', 'beta/beta.php']);

        expect(pluginManagerOn($this->root)->active())->toBe('acme/acme.php');
    });

    it('is false when no plugin is active', function (): void {
        Functions\when('get_option')->justReturn([]);

        expect(pluginManagerOn($this->root)->active())->toBeFalse();
    });
});

describe('listing by state', function (): void {
    beforeEach(function (): void {
        $this->repository = pluginManagerRepository(
            pluginManagerModule('on', ['Network' => true], active: true),
            pluginManagerModule('off', ['Network' => false], active: false),
        );
        $this->manager = pluginManagerOn($this->root, $this->repository);
    });

    it('separates active from inactive plugins', function (): void {
        expect(array_keys($this->manager->getActivePlugins()))->toBe(['on'])
            ->and(array_keys($this->manager->getInactivePlugins()))->toBe(['off']);
    });

    it('separates network-wide from single-site plugins', function (): void {
        expect(array_keys($this->manager->getNetworkWidePlugins()))->toBe(['on'])
            ->and(array_keys($this->manager->getSingleSitePlugins()))->toBe(['off']);
    });

    it('wraps every plugin in a collection', function (): void {
        expect($this->manager->collect())->toBeInstanceOf(PluginCollection::class)
            ->and($this->manager->collect()->active()->keys()->all())->toBe(['on']);
    });

    it('answers per plugin whether it is active', function (): void {
        expect($this->manager->isPluginActive('on'))->toBeTrue()
            ->and($this->manager->isPluginActive('off'))->toBeFalse()
            ->and($this->manager->isPluginActive('ghost'))->toBeFalse();
    });

    it('lists no active and no network-wide plugin without a repository', function (): void {
        expect(pluginManagerOn($this->root)->getActivePlugins())->toBe([])
            ->and(pluginManagerOn($this->root)->getNetworkWidePlugins())->toBe([]);
    });

    it('delegates the disabled list, the scan and the registration to the repository', function (): void {
        $repository = Mockery::mock(ModuleRepositoryInterface::class);
        $repository->shouldReceive('allDisabled')->once()->andReturn(['x']);
        $repository->shouldReceive('scan')->once()->andReturn(['y']);
        $repository->shouldReceive('register')->once();
        $manager = pluginManagerOn($this->root, $repository);

        expect($manager->getDisabledPlugins())->toBe(['x'])
            ->and($manager->scanPlugins())->toBe(['y']);
        $manager->registerPlugins();
    });

    it('scans nothing and registers nothing without a repository', function (): void {
        $manager = pluginManagerOn($this->root);

        expect($manager->scanPlugins())->toBe([]);
        $manager->registerPlugins();
    });
});

describe('activation', function (): void {
    it('activates a plugin and tells WordPress with its basename', function (): void {
        $module = pluginManagerModule('acme');
        Actions\expectDone('activate_plugin')->once()->with('acme/acme.php');

        pluginManagerOn($this->root, pluginManagerRepository($module))->activatePlugin('acme');

        expect($module->isActive())->toBeTrue();
    });

    it('deactivates a plugin and tells WordPress with its basename', function (): void {
        $module = pluginManagerModule('acme', active: true);
        Actions\expectDone('deactivate_plugin')->once()->with('acme/acme.php');

        pluginManagerOn($this->root, pluginManagerRepository($module))->deactivatePlugin('acme');

        expect($module->isActive())->toBeFalse();
    });

    it('enables and disables a plugin without telling WordPress', function (): void {
        $module = pluginManagerModule('acme');
        $manager = pluginManagerOn($this->root, pluginManagerRepository($module));
        Actions\expectDone('activate_plugin')->never();

        $manager->enablePlugin('acme');
        expect($module->isEnabled())->toBeTrue();

        $manager->disablePlugin('acme');
        expect($module->isEnabled())->toBeFalse();
    });

    it('fails for a plugin it does not know', function (string $method): void {
        $manager = pluginManagerOn($this->root, pluginManagerRepository());

        expect(fn () => $manager->{$method}('ghost'))->toThrow(PluginException::class, "Plugin 'ghost' not found");
    })->with(['activatePlugin', 'deactivatePlugin', 'enablePlugin', 'disablePlugin', 'getPluginInfo']);

    it('wraps what a plugin throws while activating', function (): void {
        $module = Mockery::mock(PluginModule::class)->makePartial();
        $module->shouldReceive('activate')->andThrow(new RuntimeException('boom'));
        $repository = Mockery::mock(ModuleRepositoryInterface::class);
        $repository->shouldReceive('find')->andReturn($module);

        $thrown = null;
        try {
            pluginManagerOn($this->root, $repository)->activatePlugin('acme');
        } catch (PluginException $exception) {
            $thrown = $exception;
        }

        expect($thrown?->getMessage())->toBe("Plugin 'acme' activation failed: boom")
            ->and($thrown?->getPrevious()?->getMessage())->toBe('boom');
    });

    it('wraps what a plugin throws while deactivating', function (): void {
        $module = Mockery::mock(PluginModule::class)->makePartial();
        $module->shouldReceive('deactivate')->andThrow(new RuntimeException('boom'));
        $repository = Mockery::mock(ModuleRepositoryInterface::class);
        $repository->shouldReceive('find')->andReturn($module);

        expect(fn () => pluginManagerOn($this->root, $repository)->deactivatePlugin('acme'))
            ->toThrow(PluginException::class, "Plugin 'acme' deactivation failed: boom");
    });
});

describe('getPluginInfo()', function (): void {
    it('describes a plugin from its headers', function (): void {
        $module = pluginManagerModule('acme', [
            'Name' => 'Acme',
            'Description' => 'Does things',
            'Version' => '2.0.0',
            'Author' => 'Acme Inc',
            'TextDomain' => 'acme-domain',
            'RequiresWP' => '6.4',
            'TestedUpTo' => '6.8',
            'RequiresPHP' => '8.2',
            'Network' => true,
        ], active: true);

        $info = pluginManagerOn($this->root, pluginManagerRepository($module))->getPluginInfo('acme');

        expect($info)->toMatchArray([
            'name' => 'acme',
            'description' => 'Does things',
            'version' => '2.0.0',
            'author' => 'Acme Inc',
            'path' => '/plugins/acme',
            'enabled' => true,
            'active' => true,
            'slug' => 'acme',
            'basename' => 'acme/acme.php',
            'main_file' => '/plugins/acme/acme.php',
            'text_domain' => 'acme-domain',
            'network_wide' => true,
            'requires_wp' => '6.4',
            'tested_up_to' => '6.8',
            'requires_php' => '8.2',
            'plugin_uri' => null,
            'author_uri' => null,
        ]);
    });
});

describe('validatePlugin()', function (): void {
    it('says a plugin is not found', function (): void {
        expect(pluginManagerOn($this->root, pluginManagerRepository())->validatePlugin('ghost'))
            ->toBe(['valid' => false, 'errors' => ['Plugin not found']]);
    });

    it('does not validate without a repository', function (): void {
        expect(pluginManagerOn($this->root)->validatePlugin('acme')['valid'])->toBeFalse();
    });

    it('reports a missing directory, a missing main file and missing headers', function (): void {
        $module = pluginManagerModule('acme');

        expect(pluginManagerOn($this->root, pluginManagerRepository($module))->validatePlugin('acme'))->toBe([
            'valid' => false,
            'errors' => [
                'Plugin directory does not exist',
                'Main plugin file does not exist',
                'Plugin headers are missing or invalid',
            ],
        ]);
    });

    it('accepts a plugin that has its directory, main file and headers', function (): void {
        mkdir($this->root.'/acme');
        touch($this->root.'/acme/acme.php');
        $module = new PluginModule('acme', $this->root.'/acme');
        $module->setHeaders(['Name' => 'Acme']);

        expect(pluginManagerOn($this->root, pluginManagerRepository($module))->validatePlugin('acme'))
            ->toBe(['valid' => true, 'errors' => []]);
    });
});

describe('resetCache()', function (): void {
    it('resets both the repository and the discovery', function (): void {
        $repository = Mockery::mock(ModuleRepositoryInterface::class);
        $repository->shouldReceive('resetCache')->once();
        $discovery = new class
        {
            public int $resets = 0;

            public function resetCache(): void
            {
                $this->resets++;
            }
        };
        $manager = pluginManagerOn($this->root, $repository);
        $manager->discovery = $discovery;

        $manager->resetCache();

        expect($discovery->resets)->toBe(1);
    });
});
