<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Mockery\MockInterface;
use Pollora\Discovery\Application\Services\DiscoveryManager;
use Pollora\Discovery\Domain\Models\DiscoveryLocation;
use Pollora\Discovery\UI\Console\DiscoveryClearCommand;
use Pollora\Discovery\UI\Console\DiscoveryCommand;
use Pollora\Modules\Domain\Contracts\ModuleDiscoveryOrchestratorInterface;
use Spatie\StructureDiscoverer\Cache\DiscoverCacheDriver;
use Spatie\StructureDiscoverer\Cache\NullDiscoverCacheDriver;
use Tests\Support\FakeDiscoveryEngine;
use Tests\Support\InstrumentedFakeDiscoveryEngine;

/**
 * The calls that change the state of the engine, in the order they were made.
 *
 * @return list<string>
 */
function lifecycleCalls(FakeDiscoveryEngine $engine): array
{
    return array_values(array_filter(
        $engine->calls->getArrayCopy(),
        fn (string $call): bool => in_array($call, ['clearCache', 'discover', 'apply'], true),
    ));
}

function contentModuleOrchestrator(array $laravel = [], array $framework = []): ModuleDiscoveryOrchestratorInterface&MockInterface
{
    $orchestrator = Mockery::mock(ModuleDiscoveryOrchestratorInterface::class);
    $orchestrator->shouldReceive('discoverAndReturnLaravelModules')->andReturn($laravel);
    $orchestrator->shouldReceive('discoverAndReturnFrameworkModules')->andReturn($framework);

    return $orchestrator;
}

beforeEach(function (): void {
    Artisan::registerCommand($this->app->make(DiscoveryCommand::class));
    Artisan::registerCommand($this->app->make(DiscoveryClearCommand::class));
});

describe('discovery:run', function (): void {
    it('runs every discovery, then the Laravel and framework module discoveries, and totals the items', function (): void {
        $engine = new InstrumentedFakeDiscoveryEngine(new stdClass, discovered: ['hooks' => [1, 2, 3], 'blocks' => [1]]);
        $engine->locations = [new DiscoveryLocation('Tests\\', __DIR__)];

        $manager = realDiscoveryManager($engine);

        $orchestrator = contentModuleOrchestrator(
            laravel: ['blog' => ['hooks' => [1, 2]]],
            framework: ['shop' => ['hooks' => [1], 'blocks' => [1, 2]]],
        );
        $orchestrator->shouldReceive('discoverLaravelModules')->once();
        $orchestrator->shouldReceive('applyLaravelModules')->once();
        $orchestrator->shouldReceive('discoverFrameworkModules')->once();
        $orchestrator->shouldReceive('applyFrameworkModules')->once();

        $this->app->instance(DiscoveryManager::class, $manager);
        $this->app->instance(ModuleDiscoveryOrchestratorInterface::class, $orchestrator);

        $this->artisan('discovery:run')
            ->expectsOutputToContain('Cache Status: Enabled')
            ->expectsOutputToContain('hooks: 6 items')
            ->expectsOutputToContain('blocks: 3 items')
            ->expectsOutputToContain('Scanned 1 discovery locations')
            ->expectsOutputToContain('Scanned 1 Laravel modules')
            ->expectsOutputToContain('Scanned 1 framework modules')
            ->expectsOutputToContain('Discovery process completed successfully!')
            ->assertExitCode(0);

        expect(lifecycleCalls($engine))->toBe(['discover', 'apply']);
    });

    it('says the cache is disabled for a null cache driver and does not clear it', function (): void {
        $engine = new InstrumentedFakeDiscoveryEngine(new NullDiscoverCacheDriver);
        $manager = realDiscoveryManager($engine);

        $orchestrator = contentModuleOrchestrator();
        $orchestrator->shouldReceive('discoverLaravelModules', 'applyLaravelModules', 'discoverFrameworkModules', 'applyFrameworkModules');

        $this->app->instance(DiscoveryManager::class, $manager);
        $this->app->instance(ModuleDiscoveryOrchestratorInterface::class, $orchestrator);

        $this->artisan('discovery:run', ['--clear-cache' => true])
            ->expectsOutputToContain('Cache Status: Disabled')
            ->expectsOutputToContain('Cache is disabled - skipping cache clear')
            ->assertExitCode(0);

        expect(lifecycleCalls($engine))->toBe(['discover', 'apply']);
    });

    it('clears the discovery cache first when it is asked to and the cache is enabled', function (): void {
        $engine = new InstrumentedFakeDiscoveryEngine(new stdClass);
        $manager = realDiscoveryManager($engine);

        $orchestrator = contentModuleOrchestrator();
        $orchestrator->shouldReceive('discoverLaravelModules', 'applyLaravelModules', 'discoverFrameworkModules', 'applyFrameworkModules');

        $this->app->instance(DiscoveryManager::class, $manager);
        $this->app->instance(ModuleDiscoveryOrchestratorInterface::class, $orchestrator);

        $this->artisan('discovery:run', ['--clear-cache' => true])
            ->expectsOutputToContain('Cache cleared')
            ->assertExitCode(0);

        expect(lifecycleCalls($engine))->toBe(['clearCache', 'discover', 'apply']);
    });

    it('fails without applying anything when the requested discovery is unknown', function (): void {
        $engine = new FakeDiscoveryEngine(['hooks' => []]);
        $manager = realDiscoveryManager($engine);

        $this->app->instance(DiscoveryManager::class, $manager);
        $this->app->instance(ModuleDiscoveryOrchestratorInterface::class, contentModuleOrchestrator());

        $this->artisan('discovery:run', ['--discovery' => 'nope'])
            ->expectsOutputToContain("Discovery 'nope' not found!")
            ->assertExitCode(1);

        expect(lifecycleCalls($engine))->toBe([]);
    });

    it('applies a single discovery only once the user confirms', function (): void {
        $engine = new FakeDiscoveryEngine(['hooks' => [1, 2]]);
        $manager = realDiscoveryManager($engine);

        $orchestrator = contentModuleOrchestrator();
        $orchestrator->shouldNotReceive('discoverLaravelModules');

        $this->app->instance(DiscoveryManager::class, $manager);
        $this->app->instance(ModuleDiscoveryOrchestratorInterface::class, $orchestrator);

        $this->artisan('discovery:run', ['--discovery' => 'hooks'])
            ->expectsOutputToContain("Found 2 items for 'hooks'")
            ->expectsConfirmation('Apply discovered items?', 'yes')
            ->expectsOutputToContain('Discovery applied')
            ->assertExitCode(0);

        expect(lifecycleCalls($engine))->toBe(['discover', 'apply']);
    });

    it('leaves a single discovery unapplied when the user declines', function (): void {
        $engine = new FakeDiscoveryEngine(['hooks' => []]);
        $manager = realDiscoveryManager($engine);

        $this->app->instance(DiscoveryManager::class, $manager);
        $this->app->instance(ModuleDiscoveryOrchestratorInterface::class, contentModuleOrchestrator());

        $this->artisan('discovery:run', ['--discovery' => 'hooks'])
            ->expectsConfirmation('Apply discovered items?', 'no')
            ->assertExitCode(0);

        expect(lifecycleCalls($engine))->toBe(['discover']);
    });

    it('reports the failure and exits with an error when a discovery throws', function (): void {
        $engine = (new FakeDiscoveryEngine)->failOn('discover', new RuntimeException('scan exploded'));
        $manager = realDiscoveryManager($engine);

        $this->app->instance(DiscoveryManager::class, $manager);
        $this->app->instance(ModuleDiscoveryOrchestratorInterface::class, contentModuleOrchestrator());

        $this->artisan('discovery:run')
            ->expectsOutputToContain('Discovery process failed: scan exploded')
            ->assertExitCode(1);

        expect(lifecycleCalls($engine))->toBe(['discover']);
    });

    it('prints the performance statistics of the engine when asked', function (): void {
        $stats = [
            'context' => [
                'total_classes' => 42,
                'total_discovery_executions' => 7,
                'cache_efficiency' => 88.5,
                'stats' => ['cache_hits' => 9, 'cache_misses' => 3, 'errors' => 2],
            ],
            'instance_pool' => [
                'pool_size' => 5,
                'hit_ratio_percent' => 60,
                'total_requests' => 11,
                'circular_dependencies' => 1,
                'instantiation_errors' => 4,
            ],
            'static_cache_size' => 6,
        ];
        $manager = realDiscoveryManager(new InstrumentedFakeDiscoveryEngine(new stdClass, $stats));

        $orchestrator = contentModuleOrchestrator();
        $orchestrator->shouldReceive('discoverLaravelModules', 'applyLaravelModules', 'discoverFrameworkModules', 'applyFrameworkModules');

        $this->app->instance(DiscoveryManager::class, $manager);
        $this->app->instance(ModuleDiscoveryOrchestratorInterface::class, $orchestrator);

        $this->artisan('discovery:run', ['--stats' => true])
            ->expectsOutputToContain('Classes processed: 42')
            ->expectsOutputToContain('Discovery executions: 7')
            ->expectsOutputToContain('Cache efficiency: 88.5%')
            ->expectsOutputToContain('Cache hits: 9')
            ->expectsOutputToContain('Cache misses: 3')
            ->expectsOutputToContain('Errors handled: 2')
            ->expectsOutputToContain('Pool size: 5 instances')
            ->expectsOutputToContain('Hit ratio: 60%')
            ->expectsOutputToContain('Total requests: 11')
            ->expectsOutputToContain('Circular deps avoided: 1')
            ->expectsOutputToContain('Instantiation errors: 4')
            ->expectsOutputToContain('Cached structure sets: 6')
            ->assertExitCode(0);
    });

    it('warns instead of failing when the engine has no performance statistics', function (): void {
        $manager = realDiscoveryManager(new FakeDiscoveryEngine);

        $orchestrator = contentModuleOrchestrator();
        $orchestrator->shouldReceive('discoverLaravelModules', 'applyLaravelModules', 'discoverFrameworkModules', 'applyFrameworkModules');

        $this->app->instance(DiscoveryManager::class, $manager);
        $this->app->instance(ModuleDiscoveryOrchestratorInterface::class, $orchestrator);

        $this->artisan('discovery:run', ['--stats' => true])
            ->expectsOutputToContain('Performance statistics not available')
            ->assertExitCode(0);
    });

    it('warns instead of failing when reading the statistics throws', function (): void {
        $manager = realDiscoveryManager(new InstrumentedFakeDiscoveryEngine(new stdClass, new RuntimeException('no stats today')));

        $orchestrator = contentModuleOrchestrator();
        $orchestrator->shouldReceive('discoverLaravelModules', 'applyLaravelModules', 'discoverFrameworkModules', 'applyFrameworkModules');

        $this->app->instance(DiscoveryManager::class, $manager);
        $this->app->instance(ModuleDiscoveryOrchestratorInterface::class, $orchestrator);

        $this->artisan('discovery:run', ['--stats' => true])
            ->expectsOutputToContain('Unable to retrieve performance statistics: no stats today')
            ->assertExitCode(0);
    });
});

describe('discovery:clear', function (): void {
    it('clears the persistent cache and forgets the cache entry of every location', function (): void {
        $forgotten = [];
        $driver = new class($forgotten) implements DiscoverCacheDriver
        {
            public function __construct(public array &$forgotten) {}

            public function has(string $id): bool
            {
                return false;
            }

            public function get(string $id): array
            {
                return [];
            }

            public function put(string $id, array $discovered): void {}

            public function forget(string $id): void
            {
                $this->forgotten[] = $id;
            }
        };
        $engine = new InstrumentedFakeDiscoveryEngine($driver);
        $engine->locations = [new DiscoveryLocation('Tests\\', '/some/path'), new DiscoveryLocation('App\\', '/other/path')];

        $this->app->instance(DiscoveryManager::class, realDiscoveryManager($engine));

        $this->artisan('discovery:clear')
            ->expectsOutputToContain('Cleared cache for 2 discovery locations')
            ->expectsOutputToContain('Discovery caches cleared successfully')
            ->assertExitCode(0);

        expect($forgotten)->toBe(['discovery_'.md5('/some/path'), 'discovery_'.md5('/other/path')])
            ->and(lifecycleCalls($engine))->toBe(['clearCache']);
    });

    it('still clears the manager cache and says so when the cache is disabled', function (): void {
        $engine = new InstrumentedFakeDiscoveryEngine(new NullDiscoverCacheDriver);
        $this->app->instance(DiscoveryManager::class, realDiscoveryManager($engine));

        $this->artisan('discovery:clear')
            ->expectsOutputToContain('Cache is disabled')
            ->expectsOutputToContain('No cache entries to clear')
            ->assertExitCode(0);

        expect(lifecycleCalls($engine))->toBe(['clearCache']);
    });

    it('explains that the engine has no cache driver to clear', function (): void {
        $engine = new FakeDiscoveryEngine;
        $this->app->instance(DiscoveryManager::class, realDiscoveryManager($engine));

        $this->artisan('discovery:clear')
            ->expectsOutputToContain('Spatie cache driver not available')
            ->assertExitCode(0);

        expect(lifecycleCalls($engine))->toBe(['clearCache']);
    });

    it('explains that no cache driver is configured', function (): void {
        $engine = new InstrumentedFakeDiscoveryEngine;
        $this->app->instance(DiscoveryManager::class, realDiscoveryManager($engine));

        $this->artisan('discovery:clear')
            ->expectsOutputToContain('No cache driver configured')
            ->assertExitCode(0);

        expect(lifecycleCalls($engine))->toBe(['clearCache']);
    });

    it('explains that a driver without forget cannot be cleared', function (): void {
        $engine = new InstrumentedFakeDiscoveryEngine(new stdClass);
        $this->app->instance(DiscoveryManager::class, realDiscoveryManager($engine));

        $this->artisan('discovery:clear')
            ->expectsOutputToContain('Cache driver does not support clearing')
            ->assertExitCode(0);

        expect(lifecycleCalls($engine))->toBe(['clearCache']);
    });

    it('fails with an error message when clearing throws', function (): void {
        $engine = (new FakeDiscoveryEngine)->failOn('clearCache', new RuntimeException('disk full'));
        $this->app->instance(DiscoveryManager::class, realDiscoveryManager($engine));

        $this->artisan('discovery:clear')
            ->expectsOutputToContain('Failed to clear discovery caches: disk full')
            ->assertExitCode(1);

        expect(lifecycleCalls($engine))->toBe(['clearCache']);
    });
});
