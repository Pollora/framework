<?php

declare(strict_types=1);

use Brain\Monkey\Functions;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Pollora\Doctor\Infrastructure\Providers\DoctorServiceProvider;
use Pollora\Hook\Domain\Contract\Action;
use Pollora\Hook\Domain\Contract\Filter;
use Pollora\Hook\Domain\Contract\HookInterface;
use Pollora\Modules\Application\Services\ModuleVersions;
use Pollora\Modules\Application\UseCases\ApplyModulesUseCase;
use Pollora\Modules\Application\UseCases\DiscoverModulesUseCase;
use Pollora\Modules\Domain\Contracts\ModuleDiscoveryOrchestratorInterface;
use Pollora\Modules\Infrastructure\Checks\ModuleActivationCheck;
use Pollora\Modules\Infrastructure\Providers\ModuleServiceProvider;
use Pollora\Modules\Infrastructure\Services\LeanModuleMake;
use Pollora\Modules\Infrastructure\Services\ModuleAssetManager;
use Pollora\Modules\Infrastructure\Services\ModuleAutoloader;
use Pollora\Modules\Infrastructure\Services\ModuleComponentManager;
use Pollora\Modules\Infrastructure\Services\ModuleDiscoveryOrchestrator;
use Pollora\Modules\Infrastructure\Services\ModuleRouteLoader;
use Pollora\Modules\Infrastructure\Services\ModuleTemplate;
use Pollora\Modules\UI\Http\ModulesAdminPage;
use Pollora\Modules\UI\Http\ModuleVersionsHealthCheck;
use Pollora\VersionCheck\Domain\Contracts\VersionSourceResolver;
use Pollora\VersionCheck\Infrastructure\Sources\VersionSources;

/**
 * Bind hook services that record what the provider adds to them.
 *
 * @return object{actions: array<string, callable>, filters: array<string, callable>}
 */
function recordingHooks(Application $app): object
{
    $recorder = new class
    {
        /** @var array<string, callable> */
        public array $actions = [];

        /** @var array<string, callable> */
        public array $filters = [];
    };

    foreach ([[Action::class, 'actions'], [Filter::class, 'filters']] as [$contract, $property]) {
        $hook = Mockery::mock($contract);
        $hook->shouldReceive('add')->andReturnUsing(function (string $name, callable $callback) use ($recorder, $property): HookInterface {
            $recorder->{$property}[$name] = $callback;

            return Mockery::mock(HookInterface::class);
        });
        $app->instance($contract, $hook);
    }

    return $recorder;
}

afterEach(function (): void {
    Mockery::close();
});

describe('ModuleServiceProvider registration', function (): void {
    beforeEach(function (): void {
        $this->app->register(ModuleServiceProvider::class);
    });

    it('binds the discovery orchestrator once, under its contract and the modules.discovery alias', function (): void {
        $orchestrator = $this->app->make(ModuleDiscoveryOrchestrator::class);

        expect($this->app->make(ModuleDiscoveryOrchestratorInterface::class))->toBeInstanceOf(ModuleDiscoveryOrchestrator::class)
            ->and($this->app->make('modules.discovery'))->toBe($orchestrator)
            ->and($this->app->make(ModuleDiscoveryOrchestrator::class))->toBe($orchestrator);
    });

    it('shares its services as singletons', function (string $service): void {
        expect($this->app->make($service))->toBe($this->app->make($service));
    })->with([
        ModuleAutoloader::class,
        ModuleComponentManager::class,
        ModuleAssetManager::class,
        ModuleRouteLoader::class,
        ModuleTemplate::class,
        LeanModuleMake::class,
        DiscoverModulesUseCase::class,
        ApplyModulesUseCase::class,
        VersionSources::class,
    ]);

    it('resolves the version source contract to the shared version sources', function (): void {
        expect($this->app->make(VersionSourceResolver::class))->toBeInstanceOf(VersionSources::class);
    });

    it('merges the module defaults into the modules configuration', function (): void {
        expect(config('modules'))->toBeArray()->not->toBeEmpty()
            ->and(config('modules.versions'))->toBeArray();
    });

    it('offers config/modules.php for publishing under the pollora-modules tag', function (): void {
        $paths = ServiceProvider::pathsToPublish(ModuleServiceProvider::class, 'pollora-modules');

        expect($paths)->toHaveCount(1)
            ->and(array_key_first($paths))->toEndWith('/config/modules.php')
            ->and(array_values($paths)[0])->toBe($this->app->configPath('modules.php'));
    });

    it('adds its Artisan commands when running in the console', function (): void {
        $names = array_keys(Artisan::all());

        expect($names)->toContain('pollora:make:module', 'pollora:module:connector', 'pollora:module:frontend', 'pollora:module:outdated');
    });

    it('tags the activation check for the doctor', function (): void {
        $checks = iterator_to_array($this->app->tagged(DoctorServiceProvider::CHECKS_TAG));

        expect(array_map(fn (object $c): string => $c::class, $checks))->toContain(ModuleActivationCheck::class);
    });

    it('announces that module routes are ready once the application booted', function (): void {
        $fired = 0;
        Event::listen('modules.routes.registered', function () use (&$fired): void {
            $fired++;
        });

        (new ModuleServiceProvider($this->app))->boot();

        expect($fired)->toBe(1);
    });

    it('lets the lean generator write over a module created by nwidart, and ignores empty payloads', function (): void {
        $written = [];
        $lean = Mockery::mock(LeanModuleMake::class);
        $lean->shouldReceive('writeOver')->andReturnUsing(function (object $module) use (&$written): void {
            $written[] = $module;
        });
        $this->app->instance(LeanModuleMake::class, $lean);
        $module = new stdClass;

        Event::dispatch('modules.crm.created', [$module]);
        Event::dispatch('modules.crm.created', ['not an object']);
        Event::dispatch('modules.crm.created', []);

        expect($written)->toBe([$module]);
    });
});

describe('ModuleServiceProvider admin integration', function (): void {
    it('adds no admin hook when the hook services are not bound', function (): void {
        $this->app->register(ModuleServiceProvider::class);

        expect($this->app->bound(Action::class))->toBeFalse();
    });

    it('wires the Modules screen, its Site Health tests and the daily version refresh', function (): void {
        $hooks = recordingHooks($this->app);
        $this->app->register(ModuleServiceProvider::class);

        expect(array_keys($hooks->actions))->toBe([
            'admin_menu',
            'load-plugins_page_'.ModulesAdminPage::SLUG,
            ModuleServiceProvider::REFRESH_VERSIONS_EVENT,
            'admin_init',
        ])->and(array_keys($hooks->filters))->toBe(['views_plugins', 'site_status_tests']);
    });

    it('delegates each hook to the matching service', function (): void {
        $hooks = recordingHooks($this->app);
        $this->app->register(ModuleServiceProvider::class);

        $page = Mockery::mock(ModulesAdminPage::class);
        $page->shouldReceive('addMenuPage')->once()->andReturn('menu');
        $page->shouldReceive('handleRequest')->once();
        $page->shouldReceive('addView')->once()->with(['all' => 'All'])->andReturn(['all' => 'All', 'modules' => 'Modules']);
        $this->app->instance(ModulesAdminPage::class, $page);
        $health = Mockery::mock(ModuleVersionsHealthCheck::class);
        $health->shouldReceive('addTests')->once()->with(['direct' => []])->andReturn(['direct' => [], 'modules' => []]);
        $this->app->instance(ModuleVersionsHealthCheck::class, $health);
        $versions = Mockery::mock(ModuleVersions::class);
        $versions->shouldReceive('refresh')->once();
        $this->app->instance(ModuleVersions::class, $versions);

        $hooks->actions['admin_menu']();
        $hooks->actions['load-plugins_page_'.ModulesAdminPage::SLUG]();
        $hooks->actions[ModuleServiceProvider::REFRESH_VERSIONS_EVENT]();

        expect($hooks->filters['views_plugins'](['all' => 'All']))->toBe(['all' => 'All', 'modules' => 'Modules'])
            ->and($hooks->filters['site_status_tests'](['direct' => []]))->toBe(['direct' => [], 'modules' => []]);
    });

    it('schedules the daily refresh once, a minute out, only when it is not scheduled yet', function (): void {
        $hooks = recordingHooks($this->app);
        $this->app->register(ModuleServiceProvider::class);
        $scheduled = [];
        Functions\when('wp_next_scheduled')->justReturn(false);
        Functions\when('wp_schedule_event')->alias(function (int $time, string $recurrence, string $hook) use (&$scheduled): bool {
            $scheduled[] = [$time, $recurrence, $hook];

            return true;
        });
        $before = time();

        $hooks->actions['admin_init']();

        expect($scheduled)->toHaveCount(1)
            ->and($scheduled[0][0])->toBeGreaterThanOrEqual($before + 60)->toBeLessThanOrEqual(time() + 60)
            ->and($scheduled[0][1])->toBe('daily')
            ->and($scheduled[0][2])->toBe(ModuleServiceProvider::REFRESH_VERSIONS_EVENT);

        Functions\when('wp_next_scheduled')->justReturn(time() + 3600);
        $hooks->actions['admin_init']();

        expect($scheduled)->toHaveCount(1);
    });
});
