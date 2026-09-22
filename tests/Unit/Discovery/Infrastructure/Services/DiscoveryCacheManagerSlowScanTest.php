<?php

declare(strict_types=1);

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Pollora\Application\Domain\Contracts\DebugDetectorInterface;
use Pollora\Discovery\Domain\Contracts\DiscoveryLocationInterface;
use Pollora\Discovery\Domain\Models\DiscoveryContext;
use Pollora\Discovery\Infrastructure\Services\DiscoveryCacheManager;
use Pollora\Discovery\Infrastructure\Services\ReflectionCache;
use Psr\Log\LoggerInterface;

/**
 * A location that costs seconds has to say so.
 *
 * Discovery walks a directory in full, and Symfony's Finder enumerates before
 * it filters by extension, so a `node_modules/` under a location is paid for
 * on every request that misses the cache — which, in debug mode, is every
 * request. That cost was absorbed in silence for as long as it existed: the
 * site was three seconds slower and no log line, no notice and no test said
 * anything at all.
 */
function locationAt(string $path): DiscoveryLocationInterface
{
    $location = Mockery::mock(DiscoveryLocationInterface::class);
    $location->shouldReceive('getPath')->andReturn($path);

    return $location;
}

function managerFor(bool $debug, ?LoggerInterface $logger = null): DiscoveryCacheManager
{
    $container = new Container;

    if ($logger instanceof LoggerInterface) {
        $container->instance(LoggerInterface::class, $logger);
    }

    // config() reaches for the application container; without it the
    // non-debug path cannot resolve a cache driver.
    $container->instance('config', new Repository(['structure-discoverer' => ['cache' => []]]));
    Container::setInstance($container);

    $detector = Mockery::mock(DebugDetectorInterface::class);
    $detector->shouldReceive('isDebugMode')->andReturn($debug);

    return new DiscoveryCacheManager($container, $detector);
}

function scanOf(DiscoveryCacheManager $manager, string $path): void
{
    $manager->getStructuresForLocation(locationAt($path), new DiscoveryContext(new ReflectionCache));
}

beforeEach(function (): void {
    // A real directory, because the manager really scans it.
    $this->dir = sys_get_temp_dir().'/pollora-scan-'.bin2hex(random_bytes(6));
    mkdir($this->dir, 0o777, true);

    $this->logger = Mockery::mock(LoggerInterface::class);
});

afterEach(function (): void {
    array_map(unlink(...), glob($this->dir.'/*') ?: []);
    rmdir($this->dir);
    Container::setInstance();
});

describe('slow scan warning', function (): void {
    it('says nothing about a location that scans quickly', function (): void {
        // An empty directory is the fastest scan there is; if this warns, the
        // threshold is wrong and every site gets a log line per request.
        $this->logger->shouldNotReceive('warning');

        scanOf(managerFor(true, $this->logger), $this->dir);
    });

    it('names the location, its cost and what it found', function (): void {
        $captured = null;
        $this->logger->shouldReceive('warning')->once()
            ->andReturnUsing(function (string $message) use (&$captured): void {
                $captured = $message;
            });

        $manager = managerFor(true, $this->logger);

        // Drive the threshold rather than the clock: a scan slow enough to
        // warn would mean creating tens of thousands of files in a test.
        $reflection = new ReflectionMethod($manager, 'warnIfSlow');
        $reflection->invoke($manager, locationAt('/srv/site/plugins/demo'), 1691.0, 3);

        expect($captured)->toContain('/srv/site/plugins/demo')
            ->toContain('1691ms')
            ->toContain('3 structure(s)')
            ->toContain('node_modules');
    });

    it('stays quiet below the threshold', function (): void {
        $this->logger->shouldNotReceive('warning');

        $manager = managerFor(true, $this->logger);
        (new ReflectionMethod($manager, 'warnIfSlow'))
            ->invoke($manager, locationAt('/srv/site/app'), 24.6, 10);
    });

    it('stays quiet outside debug mode', function (): void {
        // With the cache on, the cost is paid once; a warning on every cold
        // cache would be noise in a production log.
        $this->logger->shouldNotReceive('warning');

        $manager = managerFor(false, $this->logger);
        (new ReflectionMethod($manager, 'warnIfSlow'))
            ->invoke($manager, locationAt('/srv/site/plugins/demo'), 5000.0, 1);
    });

    it('does not turn a missing logger into a failure', function (): void {
        $manager = managerFor(true);

        (new ReflectionMethod($manager, 'warnIfSlow'))
            ->invoke($manager, locationAt('/srv/site/plugins/demo'), 5000.0, 1);
    })->throwsNoExceptions();
});
