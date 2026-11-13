<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Nwidart\Modules\Contracts\RepositoryInterface;
use Nwidart\Modules\Module;
use Pollora\Discovery\Application\Services\DiscoveryManager;
use Pollora\Discovery\Domain\Contracts\DiscoveryEngineInterface;
use Pollora\Discovery\Domain\Contracts\DiscoveryLocationInterface;
use Pollora\Modules\Infrastructure\Services\FrameworkModuleDiscovery;
use Pollora\Modules\Infrastructure\Services\LaravelModuleDiscovery;
use Pollora\Modules\Infrastructure\Services\ModuleDiscoveryOrchestrator;
use Psr\Log\LoggerInterface;

afterEach(fn () => Mockery::close());

function discoveryTempDir(): string
{
    $dir = sys_get_temp_dir().'/pollora_discovery_'.uniqid('', true);
    mkdir($dir, 0777, true);

    return $dir;
}

function discoveryRemoveDir(string $dir): void
{
    if (! is_dir($dir)) {
        return;
    }
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($dir);
}

function discoveryFakeModule(string $name, string $path, bool $enabled = true): Module
{
    $module = Mockery::mock(Module::class);
    $module->shouldReceive('getName')->andReturn($name);
    $module->shouldReceive('getPath')->andReturn($path);
    $module->shouldReceive('isEnabled')->andReturn($enabled);

    return $module;
}

describe('ModuleDiscoveryOrchestrator', function (): void {
    beforeEach(function (): void {
        $this->dir = discoveryTempDir();
        $this->container = new Container;
    });

    afterEach(function (): void {
        discoveryRemoveDir($this->dir);
    });

    it('applies discovery on the engine for an existing directory', function (): void {
        $engine = Mockery::mock(DiscoveryEngineInterface::class);
        $engine->shouldReceive('addLocation')->once()->with(Mockery::on(
            fn (DiscoveryLocationInterface $l): bool => $l->getPath() === $this->dir
        ))->andReturnSelf();
        $engine->shouldReceive('discover')->once()->andReturnSelf();
        $engine->shouldReceive('apply')->once()->andReturnSelf();
        $this->container->instance(DiscoveryEngineInterface::class, $engine);

        (new ModuleDiscoveryOrchestrator($this->container))->discover($this->dir);
    });

    it('does nothing when the directory is missing or no engine is bound', function (): void {
        $engine = Mockery::mock(DiscoveryEngineInterface::class);
        $engine->shouldReceive('addLocation')->never();
        $this->container->instance(DiscoveryEngineInterface::class, $engine);
        $orchestrator = new ModuleDiscoveryOrchestrator($this->container);

        $orchestrator->discover($this->dir.'/missing');

        $unbound = new ModuleDiscoveryOrchestrator(new Container);
        $unbound->discover($this->dir);
        expect($unbound->discoverAndReturn($this->dir))->toBe([]);
    });

    it('logs and swallows an engine failure during discover', function (): void {
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('error')->once()->with(
            Mockery::on(fn (string $m): bool => str_contains($m, $this->dir)),
            Mockery::type('array')
        );
        $this->container->instance(LoggerInterface::class, $logger);
        $engine = Mockery::mock(DiscoveryEngineInterface::class);
        $engine->shouldReceive('addLocation')->andThrow(new RuntimeException('boom'));
        $this->container->instance(DiscoveryEngineInterface::class, $engine);

        (new ModuleDiscoveryOrchestrator($this->container))->discover($this->dir);
    });

    it('returns what the discovery manager finds for a directory', function (): void {
        $manager = Mockery::mock(DiscoveryManager::class);
        $manager->shouldReceive('discoverAllInLocation')->once()->andReturn(['hooks' => ['a']]);
        $this->container->instance(DiscoveryManager::class, $manager);

        $result = (new ModuleDiscoveryOrchestrator($this->container))->discoverAndReturn($this->dir);

        expect($result)->toBe(['hooks' => ['a']]);
    });

    it('returns an empty array and logs when the manager fails', function (): void {
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('error')->once();
        $this->container->instance(LoggerInterface::class, $logger);
        $manager = Mockery::mock(DiscoveryManager::class);
        $manager->shouldReceive('discoverAllInLocation')->andThrow(new RuntimeException('boom'));
        $this->container->instance(DiscoveryManager::class, $manager);

        expect((new ModuleDiscoveryOrchestrator($this->container))->discoverAndReturn($this->dir))->toBe([]);
    });

    it('returns an empty array for a missing directory', function (): void {
        $manager = Mockery::mock(DiscoveryManager::class);
        $manager->shouldReceive('discoverAllInLocation')->never();
        $this->container->instance(DiscoveryManager::class, $manager);

        expect((new ModuleDiscoveryOrchestrator($this->container))->discoverAndReturn($this->dir.'/nope'))->toBe([]);
    });

    it('delegates Laravel module operations to a single lazily built discovery service', function (): void {
        $repo = Mockery::mock(RepositoryInterface::class);
        $repo->shouldReceive('allEnabled')->twice()->andReturn([]);
        $this->container->instance('modules', $repo);
        $orchestrator = new ModuleDiscoveryOrchestrator($this->container);

        $orchestrator->discoverLaravelModules();
        $orchestrator->applyLaravelModules();
        expect($orchestrator->discoverAndReturnLaravelModules())->toBe([]);
    });

    it('delegates Laravel single-module discovery by name', function (): void {
        $repo = Mockery::mock(RepositoryInterface::class);
        $repo->shouldReceive('find')->once()->with('Blog')->andReturn(null);
        $this->container->instance('modules', $repo);

        (new ModuleDiscoveryOrchestrator($this->container))->discoverLaravelModule('Blog');
    });

    it('delegates framework module operations and reports modules with a DDD structure', function (): void {
        mkdir($this->dir.'/app/Blog/Domain', 0777, true);
        mkdir($this->dir.'/app/Plain', 0777, true);
        $app = new Illuminate\Foundation\Application($this->dir);
        $manager = Mockery::mock(DiscoveryManager::class);
        $manager->shouldReceive('discoverAllInLocation')->andReturn(['x' => [1]]);
        $app->instance(DiscoveryManager::class, $manager);
        $engine = Mockery::mock(DiscoveryEngineInterface::class);
        $engine->shouldReceive('addLocation')->andReturnSelf();
        $engine->shouldReceive('discover')->andReturnSelf();
        $engine->shouldReceive('apply')->andReturnSelf();
        $app->instance(DiscoveryEngineInterface::class, $engine);
        $orchestrator = new ModuleDiscoveryOrchestrator($app);

        $orchestrator->discoverFrameworkModules();
        $orchestrator->applyFrameworkModules();
        $orchestrator->discoverFrameworkModule('Blog');

        expect($orchestrator->discoverAndReturnFrameworkModules())->toBe(['Blog' => ['Domain' => ['x' => [1]]]]);
        Container::setInstance(null);
    });
});

describe('LaravelModuleDiscovery', function (): void {
    beforeEach(function (): void {
        $this->dir = discoveryTempDir();
        mkdir($this->dir.'/app', 0777, true);
        $this->container = new Container;
        $this->repo = Mockery::mock(RepositoryInterface::class);
        $this->container->instance('modules', $this->repo);
    });

    afterEach(function (): void {
        discoveryRemoveDir($this->dir);
    });

    it('does nothing when the modules repository is not bound', function (): void {
        $discovery = new LaravelModuleDiscovery(new Container);

        $discovery->discoverLaravelModules();
        $discovery->discoverLaravelModule('Blog');

        expect($discovery->discoverAndReturnLaravelModules())->toBe([]);
    });

    it('discovers enabled modules first and applies them only on request', function (): void {
        $this->repo->shouldReceive('allEnabled')->andReturn([discoveryFakeModule('Blog', $this->dir)]);
        $engine = Mockery::mock(DiscoveryEngineInterface::class);
        $engine->shouldReceive('addLocation')->once()->andReturnSelf();
        $engine->shouldReceive('discover')->once()->andReturnSelf();
        $engine->shouldReceive('apply')->once()->andReturnSelf();
        $this->container->bind(DiscoveryEngineInterface::class, fn () => $engine);
        $discovery = new LaravelModuleDiscovery($this->container);

        $discovery->discoverLaravelModules();
        $discovery->applyLaravelModules();
    });

    it('skips modules without an app directory', function (): void {
        $empty = discoveryTempDir();
        $this->repo->shouldReceive('allEnabled')->andReturn([discoveryFakeModule('Bare', $empty)]);
        $engine = Mockery::mock(DiscoveryEngineInterface::class);
        $engine->shouldReceive('addLocation')->never();
        $this->container->bind(DiscoveryEngineInterface::class, fn () => $engine);
        $discovery = new LaravelModuleDiscovery($this->container);

        $discovery->discoverLaravelModules();
        $discovery->applyLaravelModules();
        discoveryRemoveDir($empty);
    });

    it('discovers and applies one enabled module immediately but ignores a disabled one', function (): void {
        $this->repo->shouldReceive('find')->with('On')->andReturn(discoveryFakeModule('On', $this->dir));
        $this->repo->shouldReceive('find')->with('Off')->andReturn(discoveryFakeModule('Off', $this->dir, false));
        $engine = Mockery::mock(DiscoveryEngineInterface::class);
        $engine->shouldReceive('addLocation')->once()->andReturnSelf();
        $engine->shouldReceive('discover')->once()->andReturnSelf();
        $engine->shouldReceive('apply')->once()->andReturnSelf();
        $this->container->bind(DiscoveryEngineInterface::class, fn () => $engine);
        $discovery = new LaravelModuleDiscovery($this->container);

        $discovery->discoverLaravelModule('Off');
        $discovery->discoverLaravelModule('On');
    });

    it('returns discovery results keyed by module name and omits empty ones', function (): void {
        $other = discoveryTempDir();
        mkdir($other.'/app', 0777, true);
        $this->repo->shouldReceive('allEnabled')->andReturn([
            discoveryFakeModule('Blog', $this->dir),
            discoveryFakeModule('Empty', $other),
        ]);
        $manager = Mockery::mock(DiscoveryManager::class);
        $manager->shouldReceive('discoverAllInLocation')->andReturnUsing(
            fn (DiscoveryLocationInterface $l): array => str_starts_with($l->getPath(), $this->dir) ? ['routes' => [1]] : []
        );
        $this->container->instance(DiscoveryManager::class, $manager);

        $result = (new LaravelModuleDiscovery($this->container))->discoverAndReturnLaravelModules();

        expect($result)->toBe(['Blog' => ['routes' => [1]]]);
        discoveryRemoveDir($other);
    });

    it('logs instead of throwing when the repository fails', function (): void {
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('error')->atLeast()->once();
        $this->container->instance(LoggerInterface::class, $logger);
        $this->repo->shouldReceive('allEnabled')->andThrow(new RuntimeException('x'));
        $this->repo->shouldReceive('find')->andThrow(new RuntimeException('x'));
        $discovery = new LaravelModuleDiscovery($this->container);

        $discovery->discoverLaravelModules();
        $discovery->discoverLaravelModule('Blog');

        expect($discovery->discoverAndReturnLaravelModules())->toBe([]);
    });

    it('logs and keeps going when an engine fails while discovering or applying', function (): void {
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('error')->atLeast()->once();
        $this->container->instance(LoggerInterface::class, $logger);
        $this->repo->shouldReceive('allEnabled')->andReturn([discoveryFakeModule('Blog', $this->dir)]);
        $engine = Mockery::mock(DiscoveryEngineInterface::class);
        $engine->shouldReceive('addLocation')->andReturnSelf();
        $engine->shouldReceive('discover')->andReturnSelf();
        $engine->shouldReceive('apply')->andThrow(new RuntimeException('apply failed'));
        $this->container->bind(DiscoveryEngineInterface::class, fn () => $engine);
        $discovery = new LaravelModuleDiscovery($this->container);

        $discovery->discoverLaravelModules();
        $discovery->applyLaravelModules();

        $broken = Mockery::mock(DiscoveryEngineInterface::class);
        $broken->shouldReceive('addLocation')->andThrow(new RuntimeException('nope'));
        $this->container->bind(DiscoveryEngineInterface::class, fn () => $broken);
        (new LaravelModuleDiscovery($this->container))->discoverLaravelModules();
    });

    it('exposes no path based discovery', function (): void {
        $discovery = new LaravelModuleDiscovery($this->container);

        $discovery->discover($this->dir);

        expect($discovery->discoverAndReturn($this->dir))->toBe([]);
    });
});

describe('FrameworkModuleDiscovery', function (): void {
    beforeEach(function (): void {
        $this->dir = discoveryTempDir();
        mkdir($this->dir.'/app/Blog/UI', 0777, true);
        mkdir($this->dir.'/app/Shop/Application', 0777, true);
        mkdir($this->dir.'/app/Plain', 0777, true);
        file_put_contents($this->dir.'/app/file.txt', 'x');
        $this->app = new Illuminate\Foundation\Application($this->dir);
    });

    afterEach(function (): void {
        Container::setInstance(null);
        discoveryRemoveDir($this->dir);
    });

    it('does nothing without a discovery engine or manager', function (): void {
        $discovery = new FrameworkModuleDiscovery($this->app);

        $discovery->discoverFrameworkModules();
        $discovery->discoverFrameworkModule('Blog');
        $discovery->applyFrameworkModules();

        expect($discovery->getDiscoveredModules())->toBe([])
            ->and($discovery->discoverAndReturnFrameworkModules())->toBe([]);
    });

    it('registers the whole app directory as one location under the name app', function (): void {
        $engine = Mockery::mock(DiscoveryEngineInterface::class);
        $engine->shouldReceive('addLocation')->once()->with(Mockery::on(
            fn (DiscoveryLocationInterface $l): bool => $l->getPath() === $this->dir.'/app'
        ))->andReturnSelf();
        $engine->shouldReceive('discover')->once()->andReturnSelf();
        $engine->shouldReceive('apply')->once()->andReturnSelf();
        $this->app->instance(DiscoveryEngineInterface::class, $engine);
        $discovery = new FrameworkModuleDiscovery($this->app);

        $discovery->discoverFrameworkModules();
        $discovery->applyFrameworkModules();

        expect($discovery->hasDiscoveredModule('app'))->toBeTrue()
            ->and($discovery->getModuleDiscoveryEngine('app'))->toBe($engine)
            ->and($discovery->hasDiscoveredModule('Blog'))->toBeFalse();

        $discovery->clearDiscoveredModules();

        expect($discovery->getDiscoveredModules())->toBe([])
            ->and($discovery->getModuleDiscoveryEngine('app'))->toBeNull();
    });

    it('discovers a single module only when it has a DDD layer', function (): void {
        $engine = Mockery::mock(DiscoveryEngineInterface::class);
        $engine->shouldReceive('addLocation')->once()->with(Mockery::on(
            fn (DiscoveryLocationInterface $l): bool => $l->getPath() === $this->dir.'/app/Blog'
        ))->andReturnSelf();
        $engine->shouldReceive('discover')->once()->andReturnSelf();
        $this->app->instance(DiscoveryEngineInterface::class, $engine);
        $discovery = new FrameworkModuleDiscovery($this->app);

        $discovery->discoverFrameworkModule('Plain');
        $discovery->discoverFrameworkModule('Unknown');
        $discovery->discoverFrameworkModule('Blog');

        expect(array_keys($discovery->getDiscoveredModules()))->toBe(['Blog']);
    });

    it('returns results per module and per layer, skipping empty ones', function (): void {
        $manager = Mockery::mock(DiscoveryManager::class);
        $manager->shouldReceive('discoverAllInLocation')->andReturnUsing(
            fn (DiscoveryLocationInterface $l): array => str_ends_with($l->getPath(), 'Blog/UI') ? ['routes' => [1]] : []
        );
        $this->app->instance(DiscoveryManager::class, $manager);

        $result = (new FrameworkModuleDiscovery($this->app))->discoverAndReturnFrameworkModules();

        expect($result)->toBe(['Blog' => ['UI' => ['routes' => [1]]]]);
    });

    it('logs and returns nothing when the manager fails', function (): void {
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('error')->atLeast()->once();
        $this->app->instance(LoggerInterface::class, $logger);
        $manager = Mockery::mock(DiscoveryManager::class);
        $manager->shouldReceive('discoverAllInLocation')->andThrow(new RuntimeException('boom'));
        $this->app->instance(DiscoveryManager::class, $manager);

        expect((new FrameworkModuleDiscovery($this->app))->discoverAndReturnFrameworkModules())->toBe([]);
    });

    it('logs and keeps no module when discovery or apply fail', function (): void {
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('error')->atLeast()->once();
        $this->app->instance(LoggerInterface::class, $logger);
        $engine = Mockery::mock(DiscoveryEngineInterface::class);
        $engine->shouldReceive('addLocation')->andThrow(new RuntimeException('boom'));
        $this->app->instance(DiscoveryEngineInterface::class, $engine);
        $discovery = new FrameworkModuleDiscovery($this->app);

        $discovery->discoverFrameworkModules();
        $discovery->discoverFrameworkModule('Blog');

        expect($discovery->getDiscoveredModules())->toBe([]);

        $applying = Mockery::mock(DiscoveryEngineInterface::class);
        $applying->shouldReceive('addLocation')->andReturnSelf();
        $applying->shouldReceive('discover')->andReturnSelf();
        $applying->shouldReceive('apply')->andThrow(new RuntimeException('apply'));
        $this->app->instance(DiscoveryEngineInterface::class, $applying);
        $discovery->discoverFrameworkModules();
        $discovery->applyFrameworkModules();

        expect($discovery->hasDiscoveredModule('app'))->toBeTrue();
    });

    it('exposes no path based discovery', function (): void {
        $discovery = new FrameworkModuleDiscovery($this->app);

        $discovery->discover($this->dir);

        expect($discovery->discoverAndReturn($this->dir))->toBe([]);
    });
});
