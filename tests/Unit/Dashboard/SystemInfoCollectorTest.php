<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Pollora\Dashboard\Domain\Services\SystemInfoCollector;
use Pollora\Discovery\Application\Services\DiscoveryManager;
use Pollora\Discovery\Domain\Contracts\DiscoveryEngineInterface;
use Pollora\Discovery\Domain\Contracts\DiscoveryInterface;
use Pollora\Discovery\Domain\Contracts\DiscoveryLocationInterface;
use Pollora\Discovery\Domain\Models\DiscoveryItems;
use Pollora\VersionCheck\Domain\Contracts\VersionCheckerInterface;
use Pollora\VersionCheck\Domain\Services\VersionComparator;
use Spatie\StructureDiscoverer\Cache\LaravelDiscoverCacheDriver;
use Spatie\StructureDiscoverer\Cache\NullDiscoverCacheDriver;

function createCollector(
    ?VersionComparator $comparator = null,
    ?DiscoveryManager $manager = null,
): SystemInfoCollector {
    $comparator ??= new VersionComparator(
        Mockery::mock(VersionCheckerInterface::class, [
            'getCurrentVersion' => '13.4.0',
            'getLatestVersion' => '13.4.0',
        ])
    );

    $manager ??= Mockery::mock(DiscoveryManager::class);

    return new SystemInfoCollector($comparator, $manager);
}

describe('SystemInfoCollector', function (): void {

    describe('collectFrameworkInfo', function (): void {
        it('returns current and latest version', function (): void {
            $collector = createCollector();
            $info = $collector->collectFrameworkInfo();

            expect($info)->toMatchArray([
                'current' => '13.4.0',
                'latest' => '13.4.0',
                'update_available' => false,
            ]);
        });

        it('detects update available', function (): void {
            $checker = Mockery::mock(VersionCheckerInterface::class, [
                'getCurrentVersion' => '13.3.0',
                'getLatestVersion' => '13.4.0',
            ]);

            $collector = createCollector(new VersionComparator($checker));
            $info = $collector->collectFrameworkInfo();

            expect($info['update_available'])->toBeTrue();
            expect($info['current'])->toBe('13.3.0');
            expect($info['latest'])->toBe('13.4.0');
        });

        it('handles null versions', function (): void {
            $checker = Mockery::mock(VersionCheckerInterface::class, [
                'getCurrentVersion' => null,
                'getLatestVersion' => null,
            ]);

            $collector = createCollector(new VersionComparator($checker));
            $info = $collector->collectFrameworkInfo();

            expect($info['current'])->toBeNull();
            expect($info['latest'])->toBeNull();
            expect($info['update_available'])->toBeFalse();
        });
    });

    describe('collectEnvironmentInfo', function (): void {
        it('returns PHP and Laravel versions', function (): void {
            $collector = createCollector();
            $info = $collector->collectEnvironmentInfo();

            expect($info['php'])->toBe(PHP_VERSION);
            expect($info['laravel'])->toBe(Application::VERSION);
            expect($info)->toHaveKeys(['php', 'laravel', 'wordpress']);
        });
    });

    describe('collectDiscoveryInfo', function (): void {
        it('collects post type count and labels from discovery', function (): void {
            $items = new DiscoveryItems;
            $location = Mockery::mock(DiscoveryLocationInterface::class);
            $location->shouldReceive('getKey')->andReturn('test');
            $items->add($location, ['class' => 'App\\PostTypes\\Project']);
            $items->add($location, ['class' => 'App\\PostTypes\\Service']);

            $postTypeDiscovery = Mockery::mock(DiscoveryInterface::class);
            $postTypeDiscovery->shouldReceive('getItems')->andReturn($items);

            $manager = Mockery::mock(DiscoveryManager::class);
            $manager->shouldReceive('getDiscoveredItems')
                ->with('post_types')
                ->andReturn($items->all());
            $manager->shouldReceive('getDiscoveredItems')
                ->with('taxonomies')
                ->andReturn([]);
            $manager->shouldReceive('getDiscoveredItems')
                ->with('hooks')
                ->andReturn([]);

            $collector = createCollector(manager: $manager);
            $info = $collector->collectDiscoveryInfo();

            expect($info['post_types']['count'])->toBe(2);
            expect($info['post_types']['items'][0])->toHaveKeys(['class', 'slug', 'label']);
            expect($info['post_types']['items'][0]['class'])->toBe('App\\PostTypes\\Project');
            expect($info['post_types']['items'][0]['slug'])->toBe('project');
            expect($info['post_types']['items'][1]['class'])->toBe('App\\PostTypes\\Service');
        });

        it('collects hook counts by type', function (): void {
            $manager = Mockery::mock(DiscoveryManager::class);
            $manager->shouldReceive('getDiscoveredItems')
                ->with('post_types')
                ->andReturn([]);
            $manager->shouldReceive('getDiscoveredItems')
                ->with('taxonomies')
                ->andReturn([]);
            $manager->shouldReceive('getDiscoveredItems')
                ->with('hooks')
                ->andReturn([
                    ['type' => 'action', 'class' => 'Hooks\\MyHook', 'method' => 'onInit'],
                    ['type' => 'action', 'class' => 'Hooks\\MyHook', 'method' => 'onSave'],
                    ['type' => 'filter', 'class' => 'Hooks\\MyFilter', 'method' => 'filterTitle'],
                ]);

            $collector = createCollector(manager: $manager);
            $info = $collector->collectDiscoveryInfo();

            expect($info['hooks']['count'])->toBe(3);
            expect($info['hooks']['actions'])->toBe(2);
            expect($info['hooks']['filters'])->toBe(1);
        });

        it('handles missing discovery gracefully', function (): void {
            $manager = Mockery::mock(DiscoveryManager::class);
            $manager->shouldReceive('getDiscoveredItems')->andThrow(new RuntimeException('Not found'));

            $collector = createCollector(manager: $manager);
            $info = $collector->collectDiscoveryInfo();

            expect($info['post_types']['count'])->toBe(0);
            expect($info['taxonomies']['count'])->toBe(0);
            expect($info['hooks']['count'])->toBe(0);
        });
    });

    describe('collectCacheInfo', function (): void {
        it('reports cache as disabled with NullDriver', function (): void {
            $engine = Mockery::mock(DiscoveryEngineInterface::class);
            $engine->shouldReceive('getCacheDriver')
                ->andReturn(new NullDiscoverCacheDriver);

            $manager = Mockery::mock(DiscoveryManager::class);
            $manager->shouldReceive('getEngine')->andReturn($engine);

            $collector = createCollector(manager: $manager);
            $info = $collector->collectCacheInfo();

            expect($info['enabled'])->toBeFalse();
            expect($info['driver'])->toBe('NullDiscoverCacheDriver');
        });

        it('reports cache as enabled with Laravel driver', function (): void {
            $driver = Mockery::mock(LaravelDiscoverCacheDriver::class);

            $engine = Mockery::mock(DiscoveryEngineInterface::class);
            $engine->shouldReceive('getCacheDriver')->andReturn($driver);

            $manager = Mockery::mock(DiscoveryManager::class);
            $manager->shouldReceive('getEngine')->andReturn($engine);

            $collector = createCollector(manager: $manager);
            $info = $collector->collectCacheInfo();

            expect($info['enabled'])->toBeTrue();
        });

        it('falls back gracefully on error', function (): void {
            $manager = Mockery::mock(DiscoveryManager::class);
            $manager->shouldReceive('getEngine')
                ->andThrow(new RuntimeException('No engine'));

            $collector = createCollector(manager: $manager);
            $info = $collector->collectCacheInfo();

            expect($info['driver'])->toBe('Unknown');
            expect($info['enabled'])->toBeFalse();
        });
    });

    describe('collectThemeInfo', function (): void {
        it('returns Unknown when wp_get_theme is not available', function (): void {
            $collector = createCollector();
            $info = $collector->collectThemeInfo();

            expect($info)->toMatchArray([
                'name' => 'Unknown',
                'version' => 'Unknown',
                'template' => 'Unknown',
            ]);
        });
    });

    describe('collect', function (): void {
        it('returns all sections', function (): void {
            $engine = Mockery::mock(DiscoveryEngineInterface::class);
            $engine->shouldReceive('getCacheDriver')->andReturn(null);

            $manager = Mockery::mock(DiscoveryManager::class);
            $manager->shouldReceive('getDiscoveredItems')->andReturn([]);
            $manager->shouldReceive('getEngine')->andReturn($engine);

            $collector = createCollector(manager: $manager);
            $info = $collector->collect();

            expect($info)->toHaveKeys(['framework', 'environment', 'discovery', 'cache', 'theme']);
        });
    });
});
