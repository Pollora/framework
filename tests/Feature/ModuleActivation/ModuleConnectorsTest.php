<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Nwidart\Modules\Contracts\ActivatorInterface;
use Pollora\Modules\Domain\Contracts\ModuleStateConnector;
use Pollora\Modules\Domain\Events\ModuleDisabled;
use Pollora\Modules\Domain\Events\ModuleEnabled;
use Pollora\Modules\Domain\Exceptions\ModuleLockedException;
use Pollora\Modules\Infrastructure\Activation\ConfigStateConnector;
use Pollora\Modules\Infrastructure\Activation\ConnectorActivator;
use Pollora\Modules\Infrastructure\Activation\DatabaseStateConnector;
use Pollora\Modules\Infrastructure\Activation\JsonStateConnector;
use Pollora\Modules\Infrastructure\Activation\ModuleConnectors;
use Pollora\Modules\UI\Console\ModuleConnectorCommand;

beforeEach(function (): void {
    $this->directory = sys_get_temp_dir().'/pollora-module-states-'.uniqid();
    File::ensureDirectoryExists($this->directory);
    $this->statuses = $this->directory.'/modules_statuses.json';
    file_put_contents($this->statuses, json_encode(['Crm' => true, 'Blog' => false], JSON_PRETTY_PRINT));

    config([
        'database.connections.wordpress' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => 'wp_'],
        'modules.connector' => 'json',
        'modules.connectors' => [
            'json' => ['path' => $this->statuses],
            'database' => ['option' => 'pollora_modules', 'fallback' => 'json', 'connection' => 'wordpress'],
            'config' => ['states' => ['Crm' => true], 'enabled' => 'Shop, Blog', 'disabled' => ['Crm']],
        ],
        'modules.locked' => ['enabled' => [], 'disabled' => []],
    ]);
});

afterEach(function (): void {
    File::deleteDirectory($this->directory);
    ModuleConnectors::forgetExtensions();
});

function createOptionsTable(): void
{
    resolve('db')->connection('wordpress')->getSchemaBuilder()->create('options', function ($table): void {
        $table->increments('option_id');
        $table->string('option_name')->unique();
        $table->longText('option_value');
        $table->string('autoload', 20)->default('yes');
    });
}

describe('JsonStateConnector', function (): void {
    it("reads and writes nwidart's modules_statuses.json", function (): void {
        $connector = new JsonStateConnector($this->statuses);

        $connector->set('Shop', true);
        $connector->forget('Blog');

        expect((new JsonStateConnector($this->statuses))->all())->toBe(['Crm' => true, 'Shop' => true])
            ->and($connector->writable())->toBeTrue()
            ->and($connector->persistent())->toBeFalse()
            ->and($connector->label())->toBe('JSON file');
    });

    it('reads no state from a missing file', function (): void {
        expect((new JsonStateConnector($this->directory.'/missing.json'))->all())->toBe([]);
    });
});

describe('DatabaseStateConnector', function (): void {
    it("stores the states as JSON in a non-autoloaded option, through Laravel's connection before WordPress", function (): void {
        createOptionsTable();
        $connector = (new ModuleConnectors($this->app))->make('database');

        $connector->set('Crm', true);
        $connector->set('Blog', false);

        $row = resolve('db')->connection('wordpress')->table('options')->where('option_name', 'pollora_modules')->first();

        expect(json_decode($row->option_value, true))->toBe(['Crm' => true, 'Blog' => false])
            ->and($row->autoload)->toBe('off')
            ->and((new ModuleConnectors($this->app))->make('database')->all())->toBe(['Crm' => true, 'Blog' => false])
            ->and($connector->persistent())->toBeTrue()
            ->and($connector->writable())->toBeTrue();
    });

    it('falls back on the JSON file while the options table does not exist', function (): void {
        $connector = (new ModuleConnectors($this->app))->make('database');

        expect($connector)->toBeInstanceOf(DatabaseStateConnector::class)
            ->and($connector->all())->toBe(['Crm' => true, 'Blog' => false])
            ->and($connector->usesFallback())->toBeTrue()
            ->and($connector->writable())->toBeFalse();
    });

    it('never unserializes the stored value', function (): void {
        createOptionsTable();
        resolve('db')->connection('wordpress')->table('options')->insert(['option_name' => 'pollora_modules', 'option_value' => serialize(['Crm' => true])]);

        expect((new ModuleConnectors($this->app))->make('database')->all())->toBe([]);
    });
});

describe('ConfigStateConnector', function (): void {
    it('reads the states, then the enabled and disabled lists', function (): void {
        $connector = (new ModuleConnectors($this->app))->make('config');

        expect($connector->all())->toBe(['Crm' => false, 'Shop' => true, 'Blog' => true])
            ->and($connector->writable())->toBeFalse();
    });

    it('refuses a write', function (): void {
        (new ConfigStateConnector)->set('Crm', true);
    })->throws(RuntimeException::class, 'come from the configuration');
});

describe('ModuleConnectors', function (): void {
    it('builds a project connector from its class', function (): void {
        config(['modules.connectors.memory' => ['class' => InMemoryStateConnector::class, 'states' => ['Crm' => true]]]);

        expect((new ModuleConnectors($this->app))->make('memory')->all())->toBe(['Crm' => true]);
    });

    it('builds a connector given to extend()', function (): void {
        ModuleConnectors::extend('memory', fn (Application $app, array $config): ModuleStateConnector => new InMemoryStateConnector(['states' => ['Shop' => true]]));

        expect((new ModuleConnectors($this->app))->make('memory')->all())->toBe(['Shop' => true]);
    });

    it('refuses an unknown connector', function (): void {
        (new ModuleConnectors($this->app))->make('redis');
    })->throws(InvalidArgumentException::class, 'connectors.redis.class');

    it('splits a comma-separated list of names', function (): void {
        expect(ModuleConnectors::names(' Crm, ,Shop '))->toBe(['Crm', 'Shop'])
            ->and(ModuleConnectors::names(''))->toBe([]);
    });
});

describe('ConnectorActivator', function (): void {
    it('answers with the locked state, then the connector, then disabled', function (): void {
        config(['modules.locked' => ['enabled' => ['Blog'], 'disabled' => 'Crm']]);
        $activator = new ConnectorActivator($this->app);

        expect($activator->hasStatus('Blog', true))->toBeTrue()
            ->and($activator->hasStatus('Crm', false))->toBeTrue()
            ->and($activator->hasStatus('Unknown', false))->toBeTrue()
            ->and($activator->isLocked('Crm'))->toBeTrue();
    });

    it("writes through the connector, clears nwidart's manifest and fires an event", function (): void {
        Event::fake([ModuleEnabled::class, ModuleDisabled::class]);
        $manifest = str_replace('services.php', 'modules.php', $this->app->getCachedServicesPath());
        File::ensureDirectoryExists(dirname($manifest));
        file_put_contents($manifest, '<?php return [];');

        $activator = new ConnectorActivator($this->app);
        $activator->setActiveByName('Blog', true);
        $activator->setActiveByName('Crm', false);

        expect((new JsonStateConnector($this->statuses))->all())->toBe(['Crm' => false, 'Blog' => true])
            ->and($manifest)->not->toBeFile();
        Event::assertDispatched(ModuleEnabled::class, fn (ModuleEnabled $event): bool => $event->module === 'Blog' && $event->source === 'console');
        Event::assertDispatched(ModuleDisabled::class, fn (ModuleDisabled $event): bool => $event->module === 'Crm');
    });

    it('refuses to switch a locked module, and accepts its own state', function (): void {
        config(['modules.locked.enabled' => ['Crm']]);
        $activator = new ConnectorActivator($this->app);

        $activator->setActiveByName('Crm', true);

        expect(fn () => $activator->setActiveByName('Crm', false))->toThrow(ModuleLockedException::class, 'locked enabled');
    });

    it('forgets every state on reset', function (): void {
        $activator = new ConnectorActivator($this->app);

        $activator->reset();

        expect((new JsonStateConnector($this->statuses))->all())->toBe([]);
    });
});

describe('pollora:module:connector', function (): void {
    it('copies the current states into the new connector with --import', function (): void {
        createOptionsTable();
        $this->app->instance(ActivatorInterface::class, new ConnectorActivator($this->app));
        $this->app->make(Kernel::class)->registerCommand($this->app->make(ModuleConnectorCommand::class));

        $this->artisan('pollora:module:connector', ['connector' => 'database', '--import' => true])
            ->expectsOutputToContain('2 module state(s) copied')
            ->expectsOutputToContain('MODULES_CONNECTOR=database')
            ->assertSuccessful();

        expect((new ModuleConnectors($this->app))->make('database')->all())->toBe(['Crm' => true, 'Blog' => false]);
    });

    it('refuses to import into a read-only connector', function (): void {
        $this->app->instance(ActivatorInterface::class, new ConnectorActivator($this->app));
        $this->app->make(Kernel::class)->registerCommand($this->app->make(ModuleConnectorCommand::class));

        $this->artisan('pollora:module:connector', ['connector' => 'config', '--import' => true])->assertFailed();
    });
});

if (! class_exists('InMemoryStateConnector')) {
    final class InMemoryStateConnector implements ModuleStateConnector
    {
        /** @var array<string, bool> */
        private array $states;

        /**
         * @param  array<string, mixed>  $config
         */
        public function __construct(array $config = [])
        {
            $this->states = $config['states'] ?? [];
        }

        public function all(): array
        {
            return $this->states;
        }

        public function set(string $module, bool $enabled): void
        {
            $this->states[$module] = $enabled;
        }

        public function forget(string $module): void
        {
            unset($this->states[$module]);
        }

        public function writable(): bool
        {
            return true;
        }

        public function persistent(): bool
        {
            return false;
        }

        public function label(): string
        {
            return 'Memory';
        }
    }
}

describe('the published config/modules.php', function (): void {
    it('selects the connector activator and the JSON file nwidart already uses', function (): void {
        $config = require dirname(__DIR__, 3).'/config/modules.php';

        expect($config['activator'])->toBe('pollora')
            ->and($config['activators']['pollora']['class'])->toBe(ConnectorActivator::class)
            ->and($config['connector'])->toBe('json')
            ->and($config['connectors']['json']['path'])->toBe($config['activators']['file']['statuses-file'])
            ->and($config['admin'])->toBe(['toggle' => true, 'capability' => 'activate_plugins']);
    });
});
