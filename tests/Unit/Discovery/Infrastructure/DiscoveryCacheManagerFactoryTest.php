<?php

declare(strict_types=1);

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Pollora\Application\Domain\Contracts\DebugDetectorInterface;
use Pollora\Discovery\Infrastructure\Adapters\LaravelDiscoveryCacheManagerFactory;
use Pollora\Discovery\Infrastructure\Services\DiscoveryCacheManager;
use Psr\Log\LoggerInterface;
use Spatie\StructureDiscoverer\Cache\DiscoverCacheDriver;
use Spatie\StructureDiscoverer\Cache\LaravelDiscoverCacheDriver;
use Spatie\StructureDiscoverer\Cache\NullDiscoverCacheDriver;
use Spatie\StructureDiscoverer\Cache\StaticDiscoverCacheDriver;

function factoryManager(Container $container, bool $debug = false): DiscoveryCacheManager
{
    $detector = Mockery::mock(DebugDetectorInterface::class);
    $detector->shouldReceive('isDebugMode')->andReturn($debug);

    return (new LaravelDiscoveryCacheManagerFactory($container, $detector))->make();
}

function factoryDriverOf(DiscoveryCacheManager $manager): ?DiscoverCacheDriver
{
    return (new ReflectionProperty($manager, 'cacheDriver'))->getValue($manager);
}

function factoryLoggerOf(DiscoveryCacheManager $manager): ?LoggerInterface
{
    return (new ReflectionProperty($manager, 'logger'))->getValue($manager);
}

describe('LaravelDiscoveryCacheManagerFactory', function (): void {
    it('uses no cache at all in debug mode', function (): void {
        expect(factoryDriverOf(factoryManager(new Container, debug: true)))->toBeInstanceOf(NullDiscoverCacheDriver::class);
    });

    it('uses the Laravel cache driver when nothing is configured', function (): void {
        expect(factoryDriverOf(factoryManager(new Container)))->toBeInstanceOf(LaravelDiscoverCacheDriver::class);
    });

    it('builds the driver class the configuration names', function (): void {
        $container = new Container;
        $container->instance('config', new Repository(['structure-discoverer' => ['cache' => ['driver' => StaticDiscoverCacheDriver::class]]]));

        expect(factoryDriverOf(factoryManager($container)))->toBeInstanceOf(StaticDiscoverCacheDriver::class);
    });

    it('falls back to the Laravel cache driver when the configured driver cannot be built', function (): void {
        $container = new Container;
        $container->instance('config', new Repository(['structure-discoverer' => ['cache' => ['driver' => 'App\\Missing\\Driver']]]));

        expect(factoryDriverOf(factoryManager($container)))->toBeInstanceOf(LaravelDiscoverCacheDriver::class);
    });

    it('hands the logger to the manager when there is one, and runs without when the container cannot build it', function (): void {
        $logger = Mockery::mock(LoggerInterface::class);
        $container = new Container;
        $container->instance(LoggerInterface::class, $logger);

        expect(factoryLoggerOf(factoryManager($container)))->toBe($logger);

        $broken = new Container;
        $broken->bind(LoggerInterface::class, fn (): never => throw new RuntimeException('no logger'));

        expect(factoryLoggerOf(factoryManager($broken)))->toBeNull();
    });
});
