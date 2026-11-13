<?php

declare(strict_types=1);

use Pollora\Application\Application\Services\ConsoleDetectionService;
use Pollora\Modules\Domain\Contracts\ModuleRepositoryInterface;
use Pollora\Plugin\Application\Services\PluginManager;
use Pollora\Plugin\Domain\Models\PluginModule;
use Pollora\Plugin\UI\Console\Commands\PluginListCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * @param  array<string, array<string, mixed>>  $headers  plugin name => headers
 */
function pluginListManager(array $plugins, ?Closure $throw = null): PluginManager
{
    $modules = [];
    foreach ($plugins as $name => [$headers, $active]) {
        $module = new PluginModule($name, '/plugins/'.$name);
        $module->setHeaders($headers);
        $module->setActive($active);
        $modules[$name] = $module;
    }

    $repository = Mockery::mock(ModuleRepositoryInterface::class);
    $repository->shouldReceive('all')->andReturnUsing(function () use ($modules, $throw): array {
        if ($throw instanceof Closure) {
            $throw();
        }

        return $modules;
    });

    return new PluginManager(app(), null, $repository, Mockery::mock(ConsoleDetectionService::class));
}

/**
 * @return array{0: int, 1: string}
 */
function runPluginList(PluginManager $manager, array $options = []): array
{
    $command = new PluginListCommand($manager);
    $command->setLaravel(app());

    $output = new BufferedOutput;
    $status = $command->run(new ArrayInput($options), $output);

    return [$status, $output->fetch()];
}

function pluginListFixture(): PluginManager
{
    return pluginListManager([
        'alpha' => [['Name' => 'Alpha', 'Version' => '1.0.0', 'Author' => 'Zed', 'Description' => 'First plugin', 'Network' => true], true],
        'beta' => [['Name' => 'Beta', 'Version' => '3.0.0', 'Author' => 'Amy', 'Description' => 'Second plugin, about shipping'], false],
        'gamma' => [['Name' => 'Gamma', 'Version' => '2.0.0', 'Author' => 'Zed'], true],
    ]);
}

/**
 * @return list<string> plugin names of the JSON output, in order
 */
function pluginListJsonNames(string $output): array
{
    return array_column(json_decode($output, true)['plugins'], 'name');
}

it('says so when there is no plugin to list', function (): void {
    [$status, $output] = runPluginList(pluginListManager([]));

    expect($status)->toBe(0)
        ->and($output)->toContain('No plugins found matching the criteria.');
});

it('lists the plugins as a table with a summary by default', function (): void {
    [$status, $output] = runPluginList(pluginListFixture());

    expect($status)->toBe(0)
        ->and($output)->toContain('Total: 3 | Active: 2 | Enabled: 2 | Network-wide: 1')
        ->and($output)->toContain('Name')->toContain('Text Domain')
        ->and($output)->toContain('alpha')->toContain('beta')->toContain('gamma')
        ->and($output)->toContain('Zed');
});

it('shows N/A for a plugin without an author', function (): void {
    $manager = pluginListManager(['solo' => [['Name' => 'Solo', 'Version' => '1.0.0'], false]]);

    [, $output] = runPluginList($manager);

    expect($output)->toContain('N/A')->toContain('Inactive');
});

it('lists the plugins as a list with their description and author', function (): void {
    [, $output] = runPluginList(pluginListFixture(), ['--format' => 'list']);

    expect($output)->toContain('First plugin')
        ->toContain('By: Zed')
        ->toContain('v3.0.0');
});

it('outputs JSON with the total, the filters and one entry per plugin', function (): void {
    [, $output] = runPluginList(pluginListFixture(), ['--format' => 'json', '--active' => true]);

    $data = json_decode($output, true);

    expect($data['total'])->toBe(2)
        ->and($data['filters'])->toBe(['active'])
        ->and(array_column($data['plugins'], 'name'))->toBe(['alpha', 'gamma'])
        ->and($data['plugins'][0])->toMatchArray([
            'version' => '1.0.0',
            'author' => 'Zed',
            'description' => 'First plugin',
            'active' => true,
            'network_wide' => true,
            'slug' => 'alpha',
            'basename' => 'alpha/alpha.php',
        ]);
});

describe('filters', function (): void {
    it('keeps only active or only inactive plugins', function (): void {
        expect(pluginListJsonNames(runPluginList(pluginListFixture(), ['--format' => 'json', '--inactive' => true])[1]))->toBe(['beta'])
            ->and(pluginListJsonNames(runPluginList(pluginListFixture(), ['--format' => 'json', '--active' => true])[1]))->toBe(['alpha', 'gamma']);
    });

    it('keeps only enabled or only disabled plugins', function (): void {
        expect(pluginListJsonNames(runPluginList(pluginListFixture(), ['--format' => 'json', '--enabled' => true])[1]))->toBe(['alpha', 'gamma'])
            ->and(pluginListJsonNames(runPluginList(pluginListFixture(), ['--format' => 'json', '--disabled' => true])[1]))->toBe(['beta']);
    });

    it('keeps only network-wide plugins', function (): void {
        $names = pluginListJsonNames(runPluginList(pluginListFixture(), ['--format' => 'json', '--network' => true])[1]);

        expect($names)->toBe(['alpha']);
    });

    it('keeps only the plugins of an author', function (): void {
        $names = pluginListJsonNames(runPluginList(pluginListFixture(), ['--format' => 'json', '--author' => 'Zed'])[1]);

        expect($names)->toBe(['alpha', 'gamma']);
    });

    it('searches names, descriptions and authors regardless of case', function (): void {
        $byName = pluginListJsonNames(runPluginList(pluginListFixture(), ['--format' => 'json', '--search' => 'GAMM'])[1]);
        $byDescription = pluginListJsonNames(runPluginList(pluginListFixture(), ['--format' => 'json', '--search' => 'shipping'])[1]);
        $byAuthor = pluginListJsonNames(runPluginList(pluginListFixture(), ['--format' => 'json', '--search' => 'amy'])[1]);

        expect($byName)->toBe(['gamma'])
            ->and($byDescription)->toBe(['beta'])
            ->and($byAuthor)->toBe(['beta']);
    });

    it('combines filters', function (): void {
        [, $output] = runPluginList(pluginListFixture(), ['--format' => 'json', '--active' => true, '--author' => 'Zed', '--search' => 'alp']);

        expect(pluginListJsonNames($output))->toBe(['alpha'])
            ->and(json_decode($output, true)['filters'])->toBe(['active', 'author:Zed', 'search:alp']);
    });

    it('prints the filters line of the summary', function (): void {
        [, $output] = runPluginList(pluginListFixture(), ['--active' => true, '--enabled' => true, '--author' => 'Zed']);

        expect($output)->toContain('Filters: active, enabled, author:Zed');
    });
});

describe('sorting', function (): void {
    it('sorts by name, ascending by default', function (): void {
        expect(pluginListJsonNames(runPluginList(pluginListFixture(), ['--format' => 'json'])[1]))->toBe(['alpha', 'beta', 'gamma'])
            ->and(pluginListJsonNames(runPluginList(pluginListFixture(), ['--format' => 'json', '--direction' => 'desc'])[1]))->toBe(['gamma', 'beta', 'alpha']);
    });

    it('sorts by version', function (): void {
        expect(pluginListJsonNames(runPluginList(pluginListFixture(), ['--format' => 'json', '--sort' => 'version'])[1]))->toBe(['alpha', 'gamma', 'beta'])
            ->and(pluginListJsonNames(runPluginList(pluginListFixture(), ['--format' => 'json', '--sort' => 'version', '--direction' => 'desc'])[1]))->toBe(['beta', 'gamma', 'alpha']);
    });

    it('sorts by author', function (): void {
        $names = pluginListJsonNames(runPluginList(pluginListFixture(), ['--format' => 'json', '--sort' => 'author'])[1]);

        expect($names[0])->toBe('beta');
    });

    it('sorts by status, active-enabled before inactive', function (): void {
        $ascending = pluginListJsonNames(runPluginList(pluginListFixture(), ['--format' => 'json', '--sort' => 'status'])[1]);
        $descending = pluginListJsonNames(runPluginList(pluginListFixture(), ['--format' => 'json', '--sort' => 'status', '--direction' => 'desc'])[1]);

        // "active-enabled" sorts before "inactive" alphabetically.
        expect($ascending)->toBe(['alpha', 'gamma', 'beta'])
            ->and($descending[0])->toBe('beta');
    });
});

it('reports a failure while listing plugins', function (): void {
    $manager = pluginListManager([], fn (): never => throw new RuntimeException('database offline'));

    [$status, $output] = runPluginList($manager);

    expect($status)->toBe(1)
        ->and($output)->toContain('Error listing plugins: database offline');
});
