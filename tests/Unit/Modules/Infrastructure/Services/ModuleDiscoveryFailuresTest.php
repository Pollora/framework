<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Foundation\Application;
use Nwidart\Modules\Contracts\RepositoryInterface;
use Nwidart\Modules\Module;
use Pollora\Discovery\Application\Services\DiscoveryManager;
use Pollora\Modules\Infrastructure\Services\FrameworkModuleDiscovery;
use Pollora\Modules\Infrastructure\Services\LaravelModuleDiscovery;
use Psr\Log\LoggerInterface;

/**
 * A module whose own accessors fail: nothing in discovery may let that escape.
 */
function failingDiscoveryModule(string $failingMethod, string $path = '/nowhere'): Module
{
    $module = Mockery::mock(Module::class);
    $module->shouldReceive('getName')->andReturn('Blog');

    foreach (['getPath' => $path, 'isEnabled' => true] as $method => $value) {
        $expectation = $module->shouldReceive($method);
        $method === $failingMethod ? $expectation->andThrow(new RuntimeException($method.' failed')) : $expectation->andReturn($value);
    }

    return $module;
}

function discoveryFailureLogger(string $expected): LoggerInterface
{
    $logger = Mockery::mock(LoggerInterface::class);
    $logger->shouldReceive('error')->once()->with($expected, Mockery::on(fn (array $c): bool => $c['exception'] instanceof Throwable));

    return $logger;
}

afterEach(function (): void {
    Container::setInstance();
    Mockery::close();
});

describe('LaravelModuleDiscovery failures', function (): void {
    beforeEach(function (): void {
        $this->container = new Container;
        $this->repo = Mockery::mock(RepositoryInterface::class);
        $this->container->instance('modules', $this->repo);
    });

    it('logs a module whose path cannot be read while discovering all of them', function (): void {
        $this->container->instance(LoggerInterface::class, discoveryFailureLogger('Laravel Module discovery error'));
        $this->repo->shouldReceive('allEnabled')->andReturn([failingDiscoveryModule('getPath')]);

        (new LaravelModuleDiscovery($this->container))->discoverLaravelModules();
    });

    it('logs a module that cannot say whether it is enabled when discovered by name', function (): void {
        $this->container->instance(LoggerInterface::class, discoveryFailureLogger('Laravel Module discovery error for Blog'));
        $this->repo->shouldReceive('find')->with('Blog')->andReturn(failingDiscoveryModule('isEnabled'));

        (new LaravelModuleDiscovery($this->container))->discoverLaravelModule('Blog');
    });

    it('logs a module whose path cannot be read while returning the discovery results', function (): void {
        $this->container->instance(LoggerInterface::class, discoveryFailureLogger('Laravel Module discovery error'));
        $this->repo->shouldReceive('allEnabled')->andReturn([failingDiscoveryModule('getPath')]);

        expect((new LaravelModuleDiscovery($this->container))->discoverAndReturnLaravelModules())->toBe([]);
    });

    it('logs the module and returns nothing when the discovery manager fails on it', function (): void {
        $dir = sys_get_temp_dir().'/pollora_discovery_fail_'.bin2hex(random_bytes(4));
        mkdir($dir.'/app', 0777, true);
        $manager = Mockery::mock(DiscoveryManager::class);
        $manager->shouldReceive('discoverAllInLocation')->andThrow(new RuntimeException('manager failed'));
        $this->container->instance(DiscoveryManager::class, $manager);
        $this->container->instance(LoggerInterface::class, discoveryFailureLogger('Discovery error for module Blog'));
        $module = Mockery::mock(Module::class);
        $module->shouldReceive('getName')->andReturn('Blog');
        $module->shouldReceive('getPath')->andReturn($dir);
        $this->repo->shouldReceive('allEnabled')->andReturn([$module]);

        try {
            expect((new LaravelModuleDiscovery($this->container))->discoverAndReturnLaravelModules())->toBe([]);
        } finally {
            rmdir($dir.'/app');
            rmdir($dir);
        }
    });

    it('works without a logger when the container cannot build one', function (): void {
        $this->container->bind(LoggerInterface::class, fn () => throw new RuntimeException('no logger'));
        $this->repo->shouldReceive('allEnabled')->andReturn([failingDiscoveryModule('getPath')]);

        (new LaravelModuleDiscovery($this->container))->discoverLaravelModules();

        expect($this->container->bound(LoggerInterface::class))->toBeTrue();
    });
});

describe('FrameworkModuleDiscovery failures', function (): void {
    beforeEach(function (): void {
        $this->dir = sys_get_temp_dir().'/pollora_framework_discovery_'.bin2hex(random_bytes(4));
        $this->app = new Application($this->dir);
        Container::setInstance($this->app);
    });

    it('finds no module when the app directory does not exist', function (): void {
        $this->app->instance(DiscoveryManager::class, Mockery::mock(DiscoveryManager::class));

        expect((new FrameworkModuleDiscovery($this->app))->discoverAndReturnFrameworkModules())->toBe([]);
    });

    it('works without a logger when the container cannot build one', function (): void {
        $this->app->bind(LoggerInterface::class, fn () => throw new RuntimeException('no logger'));

        expect((new FrameworkModuleDiscovery($this->app))->getDiscoveredModules())->toBe([]);
    });
});
