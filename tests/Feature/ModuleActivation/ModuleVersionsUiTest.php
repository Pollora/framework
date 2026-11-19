<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Nwidart\Modules\Contracts\ActivatorInterface;
use Pollora\Modules\Application\Services\ModuleVersions;
use Pollora\Modules\Infrastructure\Activation\ConnectorActivator;
use Pollora\Modules\UI\Console\ModuleConnectorCommand;
use Pollora\Modules\UI\Console\ModuleOutdatedCommand;
use Pollora\Modules\UI\Http\ModuleVersionsHealthCheck;

/**
 * @return array{package: string, version: string, latest: string|null, development: bool, update: bool, release_url: string|null}
 */
function versionRow(string $package, string $version, ?string $latest, bool $update = false, bool $development = false): array
{
    return ['package' => $package, 'version' => $version, 'latest' => $latest, 'development' => $development, 'update' => $update, 'release_url' => null];
}

function fakeVersions(array $rows): ModuleVersions
{
    $versions = Mockery::mock(ModuleVersions::class);
    $versions->shouldReceive('all')->andReturn($rows);
    $versions->shouldReceive('refresh')->andReturn($rows);

    return $versions;
}

afterEach(function (): void {
    Mockery::close();
});

describe('ModuleVersionsHealthCheck', function (): void {
    it('registers a direct Site Health test and keeps the others', function (): void {
        $check = new ModuleVersionsHealthCheck(fakeVersions([]));

        $tests = $check->addTests(['direct' => ['other' => ['label' => 'Other']], 'async' => ['x' => 1]]);

        expect($tests['direct'])->toHaveKeys(['other', 'pollora_modules_update'])
            ->and($tests['async'])->toBe(['x' => 1])
            ->and($tests['direct']['pollora_modules_update']['test'])->toBeCallable();
    });

    it('is good when no module has an update', function (): void {
        $check = new ModuleVersionsHealthCheck(fakeVersions([
            'Crm' => versionRow('acme/crm', '1.0.0', '1.0.0'),
            'Dev' => versionRow('acme/dev', 'dev-main', null, false, true),
        ]));

        $result = $check->test();

        expect($result['status'])->toBe('good')
            ->and($result['badge'])->toBe(['label' => 'Pollora', 'color' => 'blue'])
            ->and($result['test'])->toBe('pollora_modules_update');
    });

    it('recommends updating, naming each outdated module and the command to run', function (): void {
        $check = new ModuleVersionsHealthCheck(fakeVersions([
            'Crm' => versionRow('acme/crm', '1.0.0', '1.2.0', true),
            'Blog' => versionRow('acme/blog', '2.0.0', '2.0.0'),
            'Shop' => versionRow('acme/shop', '0.9.0', '1.0.0', true),
        ]));

        $result = $check->test();

        expect($result['status'])->toBe('recommended')
            ->and($result['badge']['color'])->toBe('orange')
            ->and($result['label'])->toStartWith('2 ')
            ->and($result['description'])->toStartWith('<ul>')
            ->and(substr_count($result['description'], '<li>'))->toBe(2)
            ->and($result['description'])->toContain('acme/crm', '1.0.0', '1.2.0', 'composer update acme/shop')
            ->and($result['description'])->not->toContain('acme/blog');
    });

    it('escapes what it prints in the description', function (): void {
        $check = new ModuleVersionsHealthCheck(fakeVersions(['<b>x</b>' => versionRow('acme/x', '1', '2', true)]));

        expect($check->test()['description'])->not->toContain('<b>x</b>');
    });
});

describe('pollora:module:outdated', function (): void {
    beforeEach(function (): void {
        $this->app->make(Kernel::class)->registerCommand($this->app->make(ModuleOutdatedCommand::class));
    });

    it('prints the refreshed versions as JSON', function (): void {
        $this->app->instance(ModuleVersions::class, fakeVersions(['Crm' => versionRow('acme/crm', '1.0.0', '1.1.0', true)]));

        $status = Artisan::call('pollora:module:outdated', ['--json' => true]);

        expect($status)->toBe(0)
            ->and(json_decode(Artisan::output(), true))->toBe(['Crm' => versionRow('acme/crm', '1.0.0', '1.1.0', true)]);
    });

    it('says so when no module is installed by Composer', function (): void {
        $this->app->instance(ModuleVersions::class, fakeVersions([]));

        $this->artisan('pollora:module:outdated')
            ->expectsOutputToContain('No module is installed by Composer')
            ->assertSuccessful();
    });

    it('tells apart development builds, updates, silent sources and up to date modules', function (): void {
        $this->app->instance(ModuleVersions::class, fakeVersions([
            'Dev' => versionRow('acme/dev', 'dev-main', '1.0.0', true, true),
            'Old' => versionRow('acme/old', '1.0.0', '2.0.0', true),
            'Mute' => versionRow('acme/mute', '1.0.0', null),
            'Fine' => versionRow('acme/fine', '2.0.0', '2.0.0'),
        ]));

        $this->artisan('pollora:module:outdated')
            ->expectsOutputToContain('development build')
            ->expectsOutputToContain('composer update acme/old')
            ->expectsOutputToContain('source did not answer')
            ->expectsOutputToContain('up to date')
            ->assertSuccessful();
    });
});

describe('pollora:module:connector reporting', function (): void {
    beforeEach(function (): void {
        $this->directory = sys_get_temp_dir().'/pollora-connector-ui-'.uniqid();
        File::ensureDirectoryExists($this->directory);
        $this->statuses = $this->directory.'/modules_statuses.json';
        file_put_contents($this->statuses, json_encode(['Crm' => true, 'Blog' => false]));
        config([
            'modules.connector' => 'json',
            'modules.connectors' => ['json' => ['path' => $this->statuses], 'config' => ['states' => []]],
            'modules.activators.file.statuses-file' => $this->statuses,
        ]);
        $this->app->make(Kernel::class)->registerCommand($this->app->make(ModuleConnectorCommand::class));
    });

    afterEach(function (): void {
        File::deleteDirectory($this->directory);
    });

    it('lists the states of the modules when no connector is asked for', function (): void {
        $this->app->instance(ActivatorInterface::class, new ConnectorActivator($this->app));

        $status = Artisan::call('pollora:module:connector');
        $output = Artisan::output();

        expect($status)->toBe(0)
            ->and($output)->toContain('Connector', 'json', 'Crm', 'enabled', 'Blog', 'disabled');
    });

    it('reads the states nwidart keeps when the connector activator is not in use', function (): void {
        $this->artisan('pollora:module:connector')
            ->expectsOutputToContain('json (nwidart FileActivator)')
            ->expectsOutputToContain('Crm')
            ->assertSuccessful();
    });

    it('fails with the reason when the connector is unknown', function (): void {
        $this->app->instance(ActivatorInterface::class, new ConnectorActivator($this->app));

        $this->artisan('pollora:module:connector', ['connector' => 'nope'])->assertFailed();
    });

    it('warns that the Pollora config must be published when nwidart keeps its own activator', function (): void {
        $this->artisan('pollora:module:connector', ['connector' => 'json'])
            ->expectsOutputToContain('vendor:publish --tag=pollora-modules')
            ->expectsOutputToContain('MODULES_CONNECTOR=json')
            ->assertSuccessful();
    });
});
