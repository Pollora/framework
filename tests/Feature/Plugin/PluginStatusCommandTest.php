<?php

declare(strict_types=1);

use Pollora\Application\Application\Services\ConsoleDetectionService;
use Pollora\Modules\Domain\Contracts\ModuleRepositoryInterface;
use Pollora\Plugin\Application\Services\PluginManager;
use Pollora\Plugin\Domain\Models\PluginModule;
use Pollora\Plugin\UI\Console\Commands\PluginStatusCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

function pluginStatusManager(?Closure $failing = null): PluginManager
{
    $modules = [];
    foreach ([
        'alpha' => [['Name' => 'Alpha', 'Version' => '1.0.0', 'Author' => 'Zed', 'Description' => 'First plugin', 'PluginURI' => 'https://example.test/alpha', 'Network' => true], true],
        'beta' => [['Name' => 'Beta', 'Version' => '3.0.0'], false],
    ] as $name => [$headers, $active]) {
        $module = new PluginModule($name, '/plugins/'.$name);
        $module->setHeaders($headers);
        $module->setActive($active);
        $modules[$name] = $module;
    }

    $repository = Mockery::mock(ModuleRepositoryInterface::class);
    $repository->shouldReceive('all')->andReturnUsing(function () use ($modules, $failing): array {
        if ($failing instanceof Closure) {
            $failing();
        }

        return $modules;
    });
    $repository->shouldReceive('find')->andReturnUsing(fn (string $name): ?PluginModule => $modules[$name] ?? null);
    $repository->shouldReceive('allEnabled')->andReturn(array_filter($modules, fn (PluginModule $m): bool => $m->isEnabled()));
    $repository->shouldReceive('allDisabled')->andReturn(array_filter($modules, fn (PluginModule $m): bool => $m->isDisabled()));

    return new PluginManager(app(), null, $repository, Mockery::mock(ConsoleDetectionService::class));
}

/**
 * @return array{0: int, 1: string}
 */
function runPluginStatus(PluginManager $manager, array $input = []): array
{
    $command = new PluginStatusCommand($manager);
    $command->setLaravel(app());

    $output = new BufferedOutput;
    $status = $command->run(new ArrayInput($input), $output);

    return [$status, $output->fetch()];
}

describe('for one plugin', function (): void {
    it('shows its details in a table', function (): void {
        [$status, $output] = runPluginStatus(pluginStatusManager(), ['plugin' => 'alpha']);

        expect($status)->toBe(0)
            ->and($output)->toContain('Plugin Details: alpha')
            ->and($output)->toContain('First plugin')
            ->and($output)->toContain('1.0.0')
            ->and($output)->toContain('Zed')
            ->and($output)->toContain('alpha/alpha.php')
            ->and($output)->toContain('https://example.test/alpha')
            ->and($output)->toContain('Active & Enabled')
            ->and($output)->toContain('Yes');
    });

    it('shows N/A for what a plugin does not declare', function (): void {
        [, $output] = runPluginStatus(pluginStatusManager(), ['plugin' => 'beta']);

        expect($output)->toContain('N/A')->toContain('Inactive')->toContain('No');
    });

    it('outputs the same details as JSON', function (): void {
        [$status, $output] = runPluginStatus(pluginStatusManager(), ['plugin' => 'alpha', '--json' => true]);

        $info = json_decode($output, true);

        expect($status)->toBe(0)
            ->and($info)->toMatchArray([
                'name' => 'alpha',
                'version' => '1.0.0',
                'author' => 'Zed',
                'active' => true,
                'network_wide' => true,
                'slug' => 'alpha',
                'plugin_uri' => 'https://example.test/alpha',
            ]);
    });

    it('fails for a plugin that does not exist', function (): void {
        [$status, $output] = runPluginStatus(pluginStatusManager(), ['plugin' => 'ghost']);

        expect($status)->toBe(1)
            ->and($output)->toContain("Plugin 'ghost' not found.");
    });
});

describe('for all plugins', function (): void {
    it('shows a summary and a table', function (): void {
        [$status, $output] = runPluginStatus(pluginStatusManager());

        expect($status)->toBe(0)
            ->and($output)->toContain('Total Plugins: 2')
            ->and($output)->toContain('Active Plugins: 1')
            ->and($output)->toContain('Enabled Plugins: 1')
            ->and($output)->toContain('Path')
            ->and($output)->toContain('/plugins/alpha')
            ->and($output)->toContain('/plugins/beta')
            ->and($output)->toContain('Active & Enabled')
            ->and($output)->toContain('Inactive');
    });

    it('shows a card per plugin with --detailed', function (): void {
        [, $output] = runPluginStatus(pluginStatusManager(), ['--detailed' => true]);

        expect($output)->toContain('Description: First plugin')
            ->toContain('Plugin URI: https://example.test/alpha')
            ->toContain('Author: Zed')
            ->toContain(str_repeat('-', 60))
            ->not->toContain('Plugin URI: https://example.test/beta');
    });

    it('outputs a JSON list with --json', function (): void {
        [, $output] = runPluginStatus(pluginStatusManager(), ['--json' => true]);

        $plugins = json_decode($output, true);

        expect(array_column($plugins, 'name'))->toBe(['alpha', 'beta'])
            ->and($plugins[1]['active'])->toBeFalse()
            ->and($plugins[0]['basename'])->toBe('alpha/alpha.php');
    });

    it('filters by state', function (string $option, array $expected): void {
        [, $output] = runPluginStatus(pluginStatusManager(), ['--json' => true, $option => true]);

        expect(array_column(json_decode($output, true), 'name'))->toBe($expected);
    })->with([
        'active' => ['--active', ['alpha']],
        'inactive' => ['--inactive', ['beta']],
        'enabled' => ['--enabled', ['alpha']],
        'disabled' => ['--disabled', ['beta']],
    ]);

    it('says so when no plugin matches', function (): void {
        $repository = Mockery::mock(ModuleRepositoryInterface::class);
        $repository->shouldReceive('all')->andReturn([]);
        $empty = new PluginManager(app(), null, $repository, Mockery::mock(ConsoleDetectionService::class));

        [$status, $output] = runPluginStatus($empty);

        expect($status)->toBe(0)
            ->and($output)->toContain('No plugins found matching the criteria.');
    });

    it('reports a failure while reading the plugins', function (): void {
        $manager = pluginStatusManager(fn (): never => throw new RuntimeException('database offline'));

        [$status, $output] = runPluginStatus($manager);

        expect($status)->toBe(1)
            ->and($output)->toContain('Error retrieving plugins: database offline');
    });
});
