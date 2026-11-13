<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Pollora\Discovery\Application\Services\DiscoveryManager;
use Pollora\Discovery\Domain\Contracts\DiscoveryEngineInterface;
use Pollora\Discovery\Domain\Contracts\DiscoveryInterface;
use Pollora\Discovery\Domain\Contracts\DiscoveryLocationInterface;
use Pollora\Discovery\Domain\Models\DiscoveryLocation;
use Pollora\Discovery\UI\Console\DiscoveryClearCommand;
use Pollora\Discovery\UI\Console\DiscoveryCommand;
use Pollora\Modules\Domain\Contracts\ModuleDiscoveryOrchestratorInterface;
use Spatie\StructureDiscoverer\Cache\DiscoverCacheDriver;
use Spatie\StructureDiscoverer\Cache\NullDiscoverCacheDriver;

/**
 * An engine without any optional capability, like a legacy engine.
 */
class ContentBareDiscoveryEngine implements DiscoveryEngineInterface
{
    /** @var list<DiscoveryLocationInterface> */
    public array $locations = [];

    public function addLocation(DiscoveryLocationInterface $location): static
    {
        return $this;
    }

    public function addLocations(array $locations): static
    {
        return $this;
    }

    public function addDiscovery(string $identifier, string|DiscoveryInterface $discovery): static
    {
        return $this;
    }

    public function addDiscoveries(array $discoveries): static
    {
        return $this;
    }

    public function discover(): static
    {
        return $this;
    }

    public function apply(): static
    {
        return $this;
    }

    public function run(): static
    {
        return $this;
    }

    public function getDiscovery(string $identifier): DiscoveryInterface
    {
        throw new RuntimeException('not used');
    }

    public function getDiscoveries(): Collection
    {
        return new Collection;
    }

    public function getLocations(): Collection
    {
        return new Collection($this->locations);
    }

    public function clearCache(): static
    {
        return $this;
    }

    public function clearLocations(): static
    {
        return $this;
    }

    public function runDiscovery(string $identifier, DiscoveryInterface $discovery): static
    {
        return $this;
    }
}

/**
 * An engine that exposes its cache driver and its statistics.
 */
class ContentRichDiscoveryEngine extends ContentBareDiscoveryEngine
{
    public function __construct(public ?object $driver = null, public mixed $stats = [])
    {
    }

    public function getCacheDriver(): ?object
    {
        return $this->driver;
    }

    public function getPerformanceStats(): array
    {
        if ($this->stats instanceof Throwable) {
            throw $this->stats;
        }

        return $this->stats;
    }
}

function contentDiscoveryManager(DiscoveryEngineInterface $engine, array $discoveries = [], array $items = []): DiscoveryManager&Mockery\MockInterface
{
    $manager = Mockery::mock(DiscoveryManager::class);
    $manager->shouldReceive('getEngine')->andReturn($engine);
    $manager->shouldReceive('getDiscoveries')->andReturn(new Collection($discoveries));
    $manager->shouldReceive('getLocations')->andReturn(new Collection([new DiscoveryLocation('Tests\\', __DIR__)]));
    $manager->shouldReceive('getDiscoveredItems')->andReturnUsing(fn (string $id): array => $items[$id] ?? []);

    return $manager;
}

function contentModuleOrchestrator(array $laravel = [], array $framework = []): ModuleDiscoveryOrchestratorInterface&Mockery\MockInterface
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
        $manager = contentDiscoveryManager(
            new ContentRichDiscoveryEngine(new stdClass),
            ['hooks' => Mockery::mock(DiscoveryInterface::class), 'blocks' => Mockery::mock(DiscoveryInterface::class)],
            ['hooks' => [1, 2, 3], 'blocks' => [1]],
        );
        $manager->shouldReceive('discover')->once()->ordered();
        $manager->shouldReceive('apply')->once()->ordered();

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
    });

    it('says the cache is disabled for a null cache driver and does not clear it', function (): void {
        $manager = contentDiscoveryManager(new ContentRichDiscoveryEngine(new NullDiscoverCacheDriver));
        $manager->shouldReceive('discover');
        $manager->shouldReceive('apply');
        $manager->shouldNotReceive('clearCache');

        $orchestrator = contentModuleOrchestrator();
        $orchestrator->shouldReceive('discoverLaravelModules', 'applyLaravelModules', 'discoverFrameworkModules', 'applyFrameworkModules');

        $this->app->instance(DiscoveryManager::class, $manager);
        $this->app->instance(ModuleDiscoveryOrchestratorInterface::class, $orchestrator);

        $this->artisan('discovery:run', ['--clear-cache' => true])
            ->expectsOutputToContain('Cache Status: Disabled')
            ->expectsOutputToContain('Cache is disabled - skipping cache clear')
            ->assertExitCode(0);
    });

    it('clears the discovery cache first when it is asked to and the cache is enabled', function (): void {
        $manager = contentDiscoveryManager(new ContentRichDiscoveryEngine(new stdClass));
        $manager->shouldReceive('clearCache')->once()->ordered();
        $manager->shouldReceive('discover')->once()->ordered();
        $manager->shouldReceive('apply')->once()->ordered();

        $orchestrator = contentModuleOrchestrator();
        $orchestrator->shouldReceive('discoverLaravelModules', 'applyLaravelModules', 'discoverFrameworkModules', 'applyFrameworkModules');

        $this->app->instance(DiscoveryManager::class, $manager);
        $this->app->instance(ModuleDiscoveryOrchestratorInterface::class, $orchestrator);

        $this->artisan('discovery:run', ['--clear-cache' => true])
            ->expectsOutputToContain('Cache cleared')
            ->assertExitCode(0);
    });

    it('fails without applying anything when the requested discovery is unknown', function (): void {
        $manager = contentDiscoveryManager(new ContentBareDiscoveryEngine);
        $manager->shouldReceive('hasDiscovery')->with('nope')->andReturn(false);
        $manager->shouldNotReceive('discover');
        $manager->shouldNotReceive('apply');

        $this->app->instance(DiscoveryManager::class, $manager);
        $this->app->instance(ModuleDiscoveryOrchestratorInterface::class, contentModuleOrchestrator());

        $this->artisan('discovery:run', ['--discovery' => 'nope'])
            ->expectsOutputToContain("Discovery 'nope' not found!")
            ->assertExitCode(1);
    });

    it('applies a single discovery only once the user confirms', function (): void {
        $manager = contentDiscoveryManager(new ContentBareDiscoveryEngine, ['hooks' => Mockery::mock(DiscoveryInterface::class)], ['hooks' => [1, 2]]);
        $manager->shouldReceive('hasDiscovery')->with('hooks')->andReturn(true);
        $manager->shouldReceive('discover')->once();
        $manager->shouldReceive('apply')->once();

        $orchestrator = contentModuleOrchestrator();
        $orchestrator->shouldNotReceive('discoverLaravelModules');

        $this->app->instance(DiscoveryManager::class, $manager);
        $this->app->instance(ModuleDiscoveryOrchestratorInterface::class, $orchestrator);

        $this->artisan('discovery:run', ['--discovery' => 'hooks'])
            ->expectsOutputToContain("Found 2 items for 'hooks'")
            ->expectsConfirmation('Apply discovered items?', 'yes')
            ->expectsOutputToContain('Discovery applied')
            ->assertExitCode(0);
    });

    it('leaves a single discovery unapplied when the user declines', function (): void {
        $manager = contentDiscoveryManager(new ContentBareDiscoveryEngine, ['hooks' => Mockery::mock(DiscoveryInterface::class)]);
        $manager->shouldReceive('hasDiscovery')->with('hooks')->andReturn(true);
        $manager->shouldReceive('discover')->once();
        $manager->shouldNotReceive('apply');

        $this->app->instance(DiscoveryManager::class, $manager);
        $this->app->instance(ModuleDiscoveryOrchestratorInterface::class, contentModuleOrchestrator());

        $this->artisan('discovery:run', ['--discovery' => 'hooks'])
            ->expectsConfirmation('Apply discovered items?', 'no')
            ->assertExitCode(0);
    });

    it('reports the failure and exits with an error when a discovery throws', function (): void {
        $manager = contentDiscoveryManager(new ContentBareDiscoveryEngine);
        $manager->shouldReceive('discover')->andThrow(new RuntimeException('scan exploded'));
        $manager->shouldNotReceive('apply');

        $this->app->instance(DiscoveryManager::class, $manager);
        $this->app->instance(ModuleDiscoveryOrchestratorInterface::class, contentModuleOrchestrator());

        $this->artisan('discovery:run')
            ->expectsOutputToContain('Discovery process failed: scan exploded')
            ->assertExitCode(1);
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
        $manager = contentDiscoveryManager(new ContentRichDiscoveryEngine(new stdClass, $stats));
        $manager->shouldReceive('discover', 'apply');

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
        $manager = contentDiscoveryManager(new ContentBareDiscoveryEngine);
        $manager->shouldReceive('discover', 'apply');

        $orchestrator = contentModuleOrchestrator();
        $orchestrator->shouldReceive('discoverLaravelModules', 'applyLaravelModules', 'discoverFrameworkModules', 'applyFrameworkModules');

        $this->app->instance(DiscoveryManager::class, $manager);
        $this->app->instance(ModuleDiscoveryOrchestratorInterface::class, $orchestrator);

        $this->artisan('discovery:run', ['--stats' => true])
            ->expectsOutputToContain('Performance statistics not available')
            ->assertExitCode(0);
    });

    it('warns instead of failing when reading the statistics throws', function (): void {
        $manager = contentDiscoveryManager(new ContentRichDiscoveryEngine(new stdClass, new RuntimeException('no stats today')));
        $manager->shouldReceive('discover', 'apply');

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
        $engine = new ContentRichDiscoveryEngine($driver);
        $engine->locations = [new DiscoveryLocation('Tests\\', '/some/path'), new DiscoveryLocation('App\\', '/other/path')];

        $manager = contentDiscoveryManager($engine);
        $manager->shouldReceive('clearCache')->once();
        $this->app->instance(DiscoveryManager::class, $manager);

        $this->artisan('discovery:clear')
            ->expectsOutputToContain('Cleared cache for 2 discovery locations')
            ->expectsOutputToContain('Discovery caches cleared successfully')
            ->assertExitCode(0);

        expect($forgotten)->toBe(['discovery_'.md5('/some/path'), 'discovery_'.md5('/other/path')]);
    });

    it('still clears the manager cache and says so when the cache is disabled', function (): void {
        $manager = contentDiscoveryManager(new ContentRichDiscoveryEngine(new NullDiscoverCacheDriver));
        $manager->shouldReceive('clearCache')->once();
        $this->app->instance(DiscoveryManager::class, $manager);

        $this->artisan('discovery:clear')
            ->expectsOutputToContain('Cache is disabled')
            ->expectsOutputToContain('No cache entries to clear')
            ->assertExitCode(0);
    });

    it('explains that the engine has no cache driver to clear', function (): void {
        $manager = contentDiscoveryManager(new ContentBareDiscoveryEngine);
        $manager->shouldReceive('clearCache')->once();
        $this->app->instance(DiscoveryManager::class, $manager);

        $this->artisan('discovery:clear')
            ->expectsOutputToContain('Spatie cache driver not available')
            ->assertExitCode(0);
    });

    it('explains that no cache driver is configured', function (): void {
        $manager = contentDiscoveryManager(new ContentRichDiscoveryEngine(null));
        $manager->shouldReceive('clearCache')->once();
        $this->app->instance(DiscoveryManager::class, $manager);

        $this->artisan('discovery:clear')
            ->expectsOutputToContain('No cache driver configured')
            ->assertExitCode(0);
    });

    it('explains that a driver without forget cannot be cleared', function (): void {
        $manager = contentDiscoveryManager(new ContentRichDiscoveryEngine(new stdClass));
        $manager->shouldReceive('clearCache')->once();
        $this->app->instance(DiscoveryManager::class, $manager);

        $this->artisan('discovery:clear')
            ->expectsOutputToContain('Cache driver does not support clearing')
            ->assertExitCode(0);
    });

    it('fails with an error message when clearing throws', function (): void {
        $manager = contentDiscoveryManager(new ContentBareDiscoveryEngine);
        $manager->shouldReceive('clearCache')->andThrow(new RuntimeException('disk full'));
        $this->app->instance(DiscoveryManager::class, $manager);

        $this->artisan('discovery:clear')
            ->expectsOutputToContain('Failed to clear discovery caches: disk full')
            ->assertExitCode(1);
    });
});
