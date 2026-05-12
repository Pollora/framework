<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Pollora\Application\Application\Services\ConsoleDetectionService;
use Pollora\Collection\Domain\Contracts\CollectionInterface;
use Pollora\Modules\Domain\Contracts\ModuleRepositoryInterface;
use Pollora\Plugin\Application\Services\PluginManager;
use Pollora\Plugin\Domain\Contracts\PluginModuleInterface;
use Pollora\Plugin\Domain\Exceptions\PluginException;
use Psr\Container\ContainerInterface;

function createPluginManager(
    ?ModuleRepositoryInterface $repository = null,
    ?ConsoleDetectionService $consoleDetection = null
): PluginManager {
    $app = Mockery::mock(ContainerInterface::class);
    $app->shouldReceive('get')->andReturn(null)->byDefault();

    if (! $consoleDetection instanceof ConsoleDetectionService) {
        $consoleDetection = Mockery::mock(ConsoleDetectionService::class);
        $consoleDetection->shouldReceive('isConsole')->andReturn(true)->byDefault();
    }

    return new PluginManager($app, null, $repository, $consoleDetection);
}

describe('PluginManager', function (): void {
    describe('instance', function (): void {
        it('returns itself', function (): void {
            $manager = createPluginManager();

            expect($manager->instance())->toBe($manager);
        });
    });

    describe('plugin', function (): void {
        it('returns null when no plugin loaded', function (): void {
            $manager = createPluginManager();

            expect($manager->plugin())->toBeNull();
        });
    });

    describe('load', function (): void {
        it('throws on empty plugin name', function (): void {
            $manager = createPluginManager();

            expect(fn () => $manager->load(''))->toThrow(PluginException::class, 'Plugin name cannot be empty.');
        });
    });

    describe('path', function (): void {
        it('returns plugin path with subpath', function (): void {
            $manager = createPluginManager();

            $path = $manager->path('my-plugin', 'config/settings.php');

            expect($path)->toContain('my-plugin/config/settings.php');
        });

        it('returns plugin base path without subpath', function (): void {
            $manager = createPluginManager();

            $path = $manager->path('my-plugin');

            expect($path)->toEndWith('my-plugin/');
        });
    });

    describe('getAllPlugins', function (): void {
        it('returns null without repository', function (): void {
            $manager = createPluginManager();

            expect($manager->getAllPlugins())->toBeNull();
        });

        it('returns collection from repository', function (): void {
            $collection = Mockery::mock(CollectionInterface::class);
            $repository = Mockery::mock(ModuleRepositoryInterface::class);
            $repository->shouldReceive('toCollection')->andReturn($collection);

            $manager = createPluginManager($repository);

            expect($manager->getAllPlugins())->toBe($collection);
        });
    });

    describe('getAllPluginsAsArray', function (): void {
        it('returns empty array without repository', function (): void {
            $manager = createPluginManager();

            expect($manager->getAllPluginsAsArray())->toBe([]);
        });

        it('returns array from repository', function (): void {
            $plugin = Mockery::mock(PluginModuleInterface::class);
            $repository = Mockery::mock(ModuleRepositoryInterface::class);
            $repository->shouldReceive('all')->andReturn(['my-plugin' => $plugin]);

            $manager = createPluginManager($repository);

            expect($manager->getAllPluginsAsArray())->toHaveCount(1);
        });
    });

    describe('getEnabledPlugins', function (): void {
        it('returns empty array without repository', function (): void {
            $manager = createPluginManager();

            expect($manager->getEnabledPlugins())->toBe([]);
        });

        it('delegates to repository allEnabled', function (): void {
            $repository = Mockery::mock(ModuleRepositoryInterface::class);
            $repository->shouldReceive('allEnabled')->andReturn(['p1', 'p2']);

            $manager = createPluginManager($repository);

            expect($manager->getEnabledPlugins())->toHaveCount(2);
        });
    });

    describe('getDisabledPlugins', function (): void {
        it('returns empty array without repository', function (): void {
            $manager = createPluginManager();

            expect($manager->getDisabledPlugins())->toBe([]);
        });
    });

    describe('findPlugin', function (): void {
        it('returns null without repository', function (): void {
            $manager = createPluginManager();

            expect($manager->findPlugin('test'))->toBeNull();
        });

        it('returns plugin from repository', function (): void {
            $plugin = Mockery::mock(PluginModuleInterface::class);
            $repository = Mockery::mock(ModuleRepositoryInterface::class);
            $repository->shouldReceive('find')->with('my-plugin')->andReturn($plugin);

            $manager = createPluginManager($repository);

            expect($manager->findPlugin('my-plugin'))->toBe($plugin);
        });

        it('returns null when repository returns non-plugin', function (): void {
            $repository = Mockery::mock(ModuleRepositoryInterface::class);
            $repository->shouldReceive('find')->with('other')->andReturn(null);

            $manager = createPluginManager($repository);

            expect($manager->findPlugin('other'))->toBeNull();
        });
    });

    describe('hasPlugin', function (): void {
        it('returns false without repository', function (): void {
            $manager = createPluginManager();

            expect($manager->hasPlugin('test'))->toBeFalse();
        });

        it('returns true when plugin exists', function (): void {
            $repository = Mockery::mock(ModuleRepositoryInterface::class);
            $repository->shouldReceive('has')->with('exists')->andReturn(true);

            $manager = createPluginManager($repository);

            expect($manager->hasPlugin('exists'))->toBeTrue();
        });
    });

    describe('getPluginCount', function (): void {
        it('returns 0 without repository', function (): void {
            $manager = createPluginManager();

            expect($manager->getPluginCount())->toBe(0);
        });

        it('returns count from repository', function (): void {
            $repository = Mockery::mock(ModuleRepositoryInterface::class);
            $repository->shouldReceive('count')->andReturn(3);

            $manager = createPluginManager($repository);

            expect($manager->getPluginCount())->toBe(3);
        });
    });

    describe('collect', function (): void {
        it('returns Collection instance', function (): void {
            $repository = Mockery::mock(ModuleRepositoryInterface::class);
            $repository->shouldReceive('all')->andReturn(['a' => 1]);

            $manager = createPluginManager($repository);
            $collection = $manager->collect();

            expect($collection)->toBeInstanceOf(Collection::class);
            expect($collection)->toHaveCount(1);
        });
    });
});
