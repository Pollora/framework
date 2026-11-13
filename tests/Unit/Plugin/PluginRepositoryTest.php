<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Pollora\Collection\Infrastructure\Adapters\LaravelCollectionAdapter;
use Pollora\Plugin\Domain\Contracts\PluginModuleInterface;
use Pollora\Plugin\Domain\Exceptions\PluginException;
use Pollora\Plugin\Domain\Models\PluginModule;
use Pollora\Plugin\Domain\Support\PluginCollection;
use Pollora\Plugin\Infrastructure\Repositories\PluginRepository;
use Pollora\Plugin\Infrastructure\Services\WordPressPluginParser;
use Psr\Log\LoggerInterface;

/**
 * A repository that reads a directory of the test and takes the active plugins from a list,
 * instead of WP_PLUGIN_DIR and is_plugin_active().
 *
 * @param  list<string>  $activePlugins
 */
function pluginRepositoryOn(string $pluginsPath, array $activePlugins = [], ?Container $container = null): PluginRepository
{
    return new class($container ?? new Container, new WordPressPluginParser, $pluginsPath, $activePlugins) extends PluginRepository
    {
        public function __construct(Container $app, WordPressPluginParser $parser, private readonly string $path, private readonly array $active)
        {
            parent::__construct($app, $parser);
        }

        protected function getPluginsBasePath(): string
        {
            return $this->path;
        }

        protected function isPluginActive(string $pluginName): bool
        {
            return in_array($pluginName, $this->active, true);
        }
    };
}

function addRepositoryPlugin(string $root, string $name, array $headers = []): void
{
    mkdir($root.'/'.$name, 0755, true);

    $lines = array_map(fn (string $key, string $value): string => ' * '.$key.': '.$value, array_keys($headers), $headers);
    file_put_contents(
        $root.'/'.$name.'/'.$name.'.php',
        "<?php\n/**\n".implode("\n", $lines)."\n */\n"
    );
}

function removePluginRepositoryTree(string $path): void
{
    if (! is_dir($path)) {
        return;
    }

    foreach (scandir($path) ?: [] as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        is_dir($path.'/'.$item) ? removePluginRepositoryTree($path.'/'.$item) : unlink($path.'/'.$item);
    }

    rmdir($path);
}

beforeEach(function (): void {
    $this->root = sys_get_temp_dir().'/pollora-plugin-repository-'.uniqid();
    mkdir($this->root, 0755, true);

    addRepositoryPlugin($this->root, 'Alpha', ['Plugin Name' => 'Alpha', 'Description' => 'Ships parcels', 'Author' => 'Zed', 'Text Domain' => 'alpha-td']);
    addRepositoryPlugin($this->root, 'beta', ['Plugin Name' => 'Beta', 'Description' => 'Counts sheep', 'Author' => 'Amy', 'Text Domain' => 'beta-td']);
    addRepositoryPlugin($this->root, 'gamma', ['Plugin Name' => 'Gamma', 'Author' => 'Zed']);
});

afterEach(function (): void {
    removePluginRepositoryTree($this->root);
});

describe('scanning', function (): void {
    it('finds the directories whose main file has a Plugin Name header', function (): void {
        addRepositoryPlugin($this->root, 'headerless', []);
        mkdir($this->root.'/no-main-file');
        touch($this->root.'/loose-file.php');

        $repository = pluginRepositoryOn($this->root);

        expect(array_keys($repository->all()))->toBe(['alpha', 'beta', 'gamma'])
            ->and($repository->count())->toBe(3);
    });

    it('keys plugins by lower-cased name and keeps the real name in the module', function (): void {
        $plugin = pluginRepositoryOn($this->root)->all()['alpha'];

        expect($plugin->getName())->toBe('Alpha')
            ->and($plugin->getAuthor())->toBe('Zed')
            ->and($plugin->getDescription())->toBe('Ships parcels')
            ->and($plugin->getPath())->toBe($this->root.'/Alpha');
    });

    it('returns no plugin when the plugins directory does not exist', function (): void {
        $repository = pluginRepositoryOn($this->root.'/missing');

        expect($repository->all())->toBe([])
            ->and($repository->count())->toBe(0);
    });

    it('marks as enabled and active the plugins WordPress reports active', function (): void {
        $repository = pluginRepositoryOn($this->root, ['beta']);

        expect($repository->find('beta')?->isActive())->toBeTrue()
            ->and($repository->find('beta')?->isEnabled())->toBeTrue()
            ->and($repository->find('alpha')?->isActive())->toBeFalse()
            ->and(array_keys($repository->allEnabled()))->toBe(['beta'])
            ->and(array_keys($repository->allDisabled()))->toBe(['alpha', 'gamma']);
    });

    it('scans once, until the cache is reset', function (): void {
        $repository = pluginRepositoryOn($this->root);
        expect($repository->count())->toBe(3);

        addRepositoryPlugin($this->root, 'delta', ['Plugin Name' => 'Delta']);
        expect($repository->count())->toBe(3);

        $repository->resetCache();
        expect($repository->count())->toBe(4);
    });

    it('keeps a plugin added before the first read', function (): void {
        $repository = pluginRepositoryOn($this->root);
        $added = new PluginModule('Epsilon', $this->root.'/Epsilon');

        $repository->add('Epsilon', $added);

        expect($repository->find('epsilon'))->toBe($added)
            ->and(array_keys($repository->all()))->toBe(['alpha', 'beta', 'gamma', 'epsilon']);
    });

    it('rescans on demand', function (): void {
        $repository = pluginRepositoryOn($this->root);
        $repository->all();
        addRepositoryPlugin($this->root, 'delta', ['Plugin Name' => 'Delta']);

        expect(array_keys($repository->scan()))->toBe(['alpha', 'beta', 'delta', 'gamma']);
    });
});

describe('lookup', function (): void {
    it('finds a plugin whatever the case of the name', function (): void {
        $repository = pluginRepositoryOn($this->root);

        expect($repository->find('ALPHA')?->getName())->toBe('Alpha')
            ->and($repository->find('alpha')?->getName())->toBe('Alpha')
            ->and($repository->find('ghost'))->toBeNull()
            ->and($repository->has('Beta'))->toBeTrue()
            ->and($repository->has('ghost'))->toBeFalse();
    });

    it('throws a helpful exception for an unknown plugin', function (): void {
        expect(fn (): PluginModuleInterface => pluginRepositoryOn($this->root)->findOrFail('ghost'))
            ->toThrow(PluginException::class, "Plugin 'ghost' not found. Check the name against the plugins that pollora:plugin:list shows.");
    });

    it('returns the plugin from findOrFail when it exists', function (): void {
        expect(pluginRepositoryOn($this->root)->findOrFail('gamma')->getName())->toBe('gamma');
    });
});

describe('collections', function (): void {
    it('wraps the plugins in a PluginCollection', function (): void {
        $repository = pluginRepositoryOn($this->root, ['Alpha']);

        expect($repository->collect())->toBeInstanceOf(PluginCollection::class)
            ->and($repository->collect()->active()->keys()->all())->toBe(['alpha'])
            ->and($repository->toCollection())->toBeInstanceOf(LaravelCollectionAdapter::class);
    });
});

describe('filters', function (): void {
    it('filters by enabled status', function (): void {
        $repository = pluginRepositoryOn($this->root, ['Alpha', 'gamma']);

        expect(array_keys($repository->getByStatus(true)))->toBe(['alpha', 'gamma'])
            ->and(array_keys($repository->getByStatus(false)))->toBe(['beta']);
    });

    it('filters by named status', function (string $status, array $expected): void {
        $repository = pluginRepositoryOn($this->root, ['Alpha']);

        expect(array_keys($repository->getByCustomStatus($status)))->toBe($expected);
    })->with([
        'active' => ['active', ['alpha']],
        'inactive' => ['inactive', ['beta', 'gamma']],
        'enabled' => ['enabled', ['alpha']],
        'disabled' => ['disabled', ['beta', 'gamma']],
        'unknown means everything' => ['whatever', ['alpha', 'beta', 'gamma']],
    ]);

    it('filters by author and by text domain', function (): void {
        $repository = pluginRepositoryOn($this->root);

        expect(array_keys($repository->getByAuthor('Zed')))->toBe(['alpha', 'gamma'])
            ->and(array_keys($repository->getByTextDomain('beta-td')))->toBe(['beta'])
            ->and($repository->getByAuthor('Nobody'))->toBe([]);
    });

    it('searches names and descriptions regardless of case', function (): void {
        $repository = pluginRepositoryOn($this->root);

        expect(array_keys($repository->search('ALP')))->toBe(['alpha'])
            ->and(array_keys($repository->search('sheep')))->toBe(['beta'])
            ->and($repository->search('zzz'))->toBe([]);
    });

    it('orders ascending or descending', function (): void {
        $repository = pluginRepositoryOn($this->root);

        expect(array_keys($repository->getOrdered()))->toBe(['alpha', 'beta', 'gamma'])
            ->and(array_keys($repository->getOrdered('desc')))->toBe(['gamma', 'beta', 'alpha']);
    });
});

describe('adding and removing', function (): void {
    it('adds a plugin under its lower-cased name', function (): void {
        $repository = pluginRepositoryOn($this->root);
        $repository->all();

        $extra = new PluginModule('Extra', '/somewhere/Extra');

        $repository->add('Extra', $extra);

        expect($repository->find('extra'))->toBe($extra);
    });

    it('removes a plugin, and says whether it was there', function (): void {
        $repository = pluginRepositoryOn($this->root);
        $repository->all();

        expect($repository->remove('ALPHA'))->toBeTrue()
            ->and($repository->has('alpha'))->toBeFalse()
            ->and($repository->remove('alpha'))->toBeFalse();
    });
});

describe('register() and boot()', function (): void {
    it('registers every plugin and boots only the enabled ones', function (): void {
        $registered = [];
        $booted = [];
        $repository = pluginRepositoryOn($this->root);
        foreach (['on' => true, 'off' => false] as $name => $enabled) {
            $module = new class($name, '/p/'.$name, $registered, $booted) extends PluginModule
            {
                public function __construct(string $name, string $path, private array &$registered, private array &$booted)
                {
                    parent::__construct($name, $path);
                }

                public function register(): void
                {
                    $this->registered[] = $this->getName();
                }

                public function boot(): void
                {
                    $this->booted[] = $this->getName();
                }
            };
            $module->setEnabled($enabled);
            $repository->add($name, $module);
        }

        // Add the plugins before the scan would overwrite them.
        (new ReflectionProperty($repository, 'cached'))->setValue($repository, true);

        $repository->register();
        $repository->boot();

        expect($registered)->toBe(['on', 'off'])
            ->and($booted)->toBe(['on']);
    });

    it('logs a plugin that fails to register or boot and goes on with the others', function (): void {
        $errors = [];
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('error')->andReturnUsing(function (string $message) use (&$errors): void {
            $errors[] = $message;
        });
        $container = new Container;
        $container->instance(LoggerInterface::class, $logger);

        $failing = new class('failing', '/p/failing') extends PluginModule
        {
            public function register(): void
            {
                throw new RuntimeException('cannot register');
            }

            public function boot(): void
            {
                throw new RuntimeException('cannot boot');
            }
        };
        $failing->enable();

        $fine = new class('fine', '/p/fine') extends PluginModule
        {
            public bool $registered = false;

            public function register(): void
            {
                $this->registered = true;
            }
        };
        $fine->enable();

        $repository = pluginRepositoryOn($this->root, container: $container);
        $repository->add('failing', $failing);
        $repository->add('fine', $fine);
        (new ReflectionProperty($repository, 'cached'))->setValue($repository, true);

        $repository->register();
        $repository->boot();

        expect($fine->registered)->toBeTrue()
            ->and($errors)->toBe([
                "[PluginRepository] Failed to register plugin 'failing': cannot register",
                "[PluginRepository] Failed to boot plugin 'failing': cannot boot",
            ]);
    });

    it('does not fail when nobody can log', function (): void {
        $failing = new class('failing', '/p/failing') extends PluginModule
        {
            public function register(): void
            {
                throw new RuntimeException('cannot register');
            }
        };
        $repository = pluginRepositoryOn($this->root);
        $repository->add('failing', $failing);
        (new ReflectionProperty($repository, 'cached'))->setValue($repository, true);

        $repository->register();

        expect($repository->has('failing'))->toBeTrue();
    });
});
