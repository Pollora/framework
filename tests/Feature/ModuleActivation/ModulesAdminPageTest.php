<?php

declare(strict_types=1);

use Brain\Monkey\Functions;
use Illuminate\Support\Facades\File;
use Nwidart\Modules\Contracts\ActivatorInterface;
use Pollora\Modules\Application\Services\ModuleStates;
use Pollora\Modules\Application\Services\ModuleVersions;
use Pollora\Modules\Infrastructure\Activation\ConnectorActivator;
use Pollora\Modules\Infrastructure\Activation\JsonStateConnector;
use Pollora\Modules\UI\Http\ModulesAdminPage;
use Pollora\VersionCheck\Infrastructure\Sources\HttpGet;
use Pollora\VersionCheck\Infrastructure\Sources\VersionSources;

/**
 * A module as nwidart/laravel-modules hands it out, switched through the activator.
 */
function fakeModule(string $name, ActivatorInterface $activator): object
{
    return new readonly class($name, $activator)
    {
        public function __construct(private string $name, private ActivatorInterface $activator) {}

        public function getName(): string
        {
            return $this->name;
        }

        public function getDescription(): string
        {
            return $this->name.' module';
        }

        public function getPath(): string
        {
            return base_path('Modules/'.$this->name);
        }

        public function isEnabled(): bool
        {
            return $this->activator->hasStatus($this->name, true);
        }

        public function enable(): void
        {
            $this->activator->setActiveByName($this->name, true);
        }

        public function disable(): void
        {
            $this->activator->setActiveByName($this->name, false);
        }
    };
}

beforeEach(function (): void {
    Functions\stubEscapeFunctions();
    Functions\stubs([
        'admin_url' => fn (string $path = ''): string => 'https://example.test/wp-admin/'.$path,
        'wp_json_encode' => fn (mixed $value): string => (string) json_encode($value),
        'wp_nonce_field' => fn (): string => '',
        'check_admin_referer' => true,
        'sanitize_text_field' => trim(...),
        'sanitize_key' => strtolower(...),
        'wp_unslash' => fn (mixed $value): mixed => $value,
        'get_current_user_id' => 1,
        'get_transient' => false,
    ]);
    Functions\when('current_user_can')->justReturn(true);

    $this->directory = sys_get_temp_dir().'/pollora-modules-admin-'.uniqid();
    File::ensureDirectoryExists($this->directory);
    $this->statuses = $this->directory.'/modules_statuses.json';
    file_put_contents($this->statuses, json_encode(['Crm' => true, 'Blog' => false, 'Shop' => true]));

    config([
        'modules.connector' => 'json',
        'modules.connectors.json.path' => $this->statuses,
        'modules.locked' => ['enabled' => ['Shop'], 'disabled' => []],
        'modules.admin' => ['toggle' => true, 'capability' => 'activate_plugins'],
    ]);

    $activator = new ConnectorActivator($this->app);
    $this->app->instance(ActivatorInterface::class, $activator);
    $modules = ['Crm' => fakeModule('Crm', $activator), 'Blog' => fakeModule('Blog', $activator), 'Shop' => fakeModule('Shop', $activator)];

    $this->app->instance('modules', new readonly class($modules)
    {
        public function __construct(private array $modules) {}

        public function all(): array
        {
            return $this->modules;
        }

        public function find(string $name): ?object
        {
            return $this->modules[$name] ?? null;
        }
    });

    $this->app->instance(VersionSources::class, new VersionSources(new HttpGet, $this->directory.'/composer.json'));

    $this->page = $this->app->make(ModulesAdminPage::class);
});

afterEach(function (): void {
    File::deleteDirectory($this->directory);
});

describe('ModuleStates', function (): void {
    it('lists every module with its state, lock and why it cannot be switched', function (): void {
        $modules = $this->app->make(ModuleStates::class)->all();

        expect(array_column($modules, 'name'))->toBe(['Blog', 'Crm', 'Shop'])
            ->and(array_column($modules, 'enabled'))->toBe([false, true, true])
            ->and($modules[2]['locked'])->toBeTrue()
            ->and($modules[2]['toggleable'])->toBeFalse()
            ->and($modules[2]['reason'])->toContain('Locked enabled')
            ->and($modules[0]['toggleable'])->toBeTrue();
    });

    it('turns every switch off with MODULES_ADMIN_TOGGLE=false', function (): void {
        config(['modules.admin.toggle' => 'false']);
        $states = $this->app->make(ModuleStates::class);

        expect(array_column($states->all(), 'toggleable'))->toBe([false, false, false])
            ->and(fn () => $states->switch('Blog', true))->toThrow(RuntimeException::class, 'MODULES_ADMIN_TOGGLE');
    });

    it("reads the state from nwidart's JSON file when the connector activator is not in use", function (): void {
        $this->app->instance(ActivatorInterface::class, Mockery::mock(ActivatorInterface::class));
        config(['modules.activators.file.statuses-file' => $this->statuses]);

        expect($this->app->make(ModuleStates::class)->connector())->toBeInstanceOf(JsonStateConnector::class);
    });
});

describe('ModulesAdminPage', function (): void {
    it('disables a module from its row and says when it applies', function (): void {
        $notice = $this->page->switchFromRequest(['toggle' => 'disable:Crm']);

        expect($notice['type'])->toBe('success')
            ->and($notice['messages'][0])->toBe('Crm disabled. The change applies from the next request.')
            ->and((new JsonStateConnector($this->statuses))->all()['Crm'])->toBeFalse();
    });

    it('enables the selected modules with the bulk action, and reports a locked one', function (): void {
        $notice = $this->page->switchFromRequest(['bulk_action' => 'disable', 'modules' => ['Crm', 'Shop']]);

        expect($notice['type'])->toBe('warning')
            ->and($notice['messages'][0])->toBe('Crm disabled. The change applies from the next request.')
            ->and($notice['messages'][1])->toContain('locked enabled');
    });

    it('refuses a user without the capability', function (): void {
        Functions\when('current_user_can')->justReturn(false);

        expect($this->page->switchFromRequest(['toggle' => 'enable:Blog'])['type'])->toBe('error')
            ->and((new JsonStateConnector($this->statuses))->all()['Blog'])->toBeFalse();
    });

    it('checks the nonce before switching', function (): void {
        $nonces = [];
        Functions\when('check_admin_referer')->alias(function (string $action) use (&$nonces): bool {
            $nonces[] = $action;

            return true;
        });

        $this->page->switchFromRequest(['toggle' => 'enable:Blog']);

        expect($nonces)->toBe(['pollora-modules']);
    });

    it('adds Modules (n) to the plugin views', function (): void {
        $views = $this->page->addView(['all' => '<a>All</a>']);

        expect($views['pollora-modules'])->toContain('plugins.php?page=pollora-modules')
            ->toContain('(3)');
    });

    it('lists the modules, warns that a JSON file does not survive a deployment, and confirms each switch', function (): void {
        ob_start();
        $this->page->render();
        $html = (string) ob_get_clean();

        expect($html)->toContain('Stored in modules_statuses.json — the next deployment resets it.')
            ->toContain('value="disable:Crm"')
            ->toContain('value="enable:Blog"')
            ->toContain('onclick="return confirm(')
            ->toContain('Locked enabled')
            ->toContain('name="modules[]" value="Shop" disabled')
            ->not->toContain('value="disable:Shop"');
    });

    it('shows the version of a module installed by Composer and its update, nothing for a local one', function (): void {
        $versions = Mockery::mock(ModuleVersions::class);
        $versions->shouldReceive('all')->andReturn(['Crm' => [
            'package' => 'acme/crm', 'version' => '1.3.0', 'latest' => '1.4.0', 'development' => false, 'update' => true,
            'release_url' => 'https://packagist.org/packages/acme/crm#1.4.0',
        ]]);
        $this->app->instance(ModuleVersions::class, $versions);

        ob_start();
        $this->app->make(ModulesAdminPage::class)->render();
        $html = (string) ob_get_clean();

        expect($html)->toContain('1.3.0 · <strong><a href="https://packagist.org/packages/acme/crm#1.4.0"')
            ->toContain('1.4.0 available')
            ->toContain('<code>composer update acme/crm</code>')
            ->and(substr_count($html, 'composer update'))->toBe(1);
    });

    it('explains how to switch modules when the state cannot be written', function (): void {
        chmod($this->statuses, 0444);

        ob_start();
        $this->page->render();
        $html = (string) ob_get_clean();
        chmod($this->statuses, 0644);

        if (is_writable($this->statuses)) {
            // Running as root: the file stays writable whatever its mode
            expect(true)->toBeTrue();

            return;
        }

        expect($html)->toContain('cannot be written here')
            ->toContain('pollora:module:connector database --import')
            ->not->toContain('value="disable:Crm"');
    });
});
