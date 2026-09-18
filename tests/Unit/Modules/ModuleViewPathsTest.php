<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Filesystem\Filesystem;
use Illuminate\View\Factory;
use Illuminate\View\FileViewFinder;
use Pollora\Modules\Infrastructure\Services\ModuleAssetManager;

/**
 * A module's view paths must end up in the finder in the order they are
 * declared. Each is prepended, so registering them front to back reverses them,
 * and a theme's root — which holds only the stub PHP templates WordPress needs
 * to consider the theme valid — ends up outranking resources/views and
 * shadowing every Blade view the theme ships.
 */
function moduleWithViewPaths(string $type, array $directories): array
{
    $modulePath = sys_get_temp_dir().'/pollora-module-'.uniqid();

    foreach ($directories as $directory) {
        mkdir($modulePath.'/'.$directory, 0755, true);
    }

    $finder = new FileViewFinder(new Filesystem, []);

    $factory = Mockery::mock(Factory::class);
    $factory->shouldReceive('getFinder')->andReturn($finder);
    $factory->shouldReceive('addNamespace')->andReturnNull();

    $app = new Container;
    $app->instance('view', $factory);

    (new ModuleAssetManager($app))->registerModuleViewPaths($modulePath, $type, 'demo');

    return [$modulePath, array_map(
        fn (string $path): string => str_replace($modulePath.'/', '', $path),
        $finder->getPaths()
    )];
}

function removeModule(string $modulePath): void
{
    foreach (['resources/views', 'resources', 'views', ''] as $directory) {
        $path = rtrim($modulePath.'/'.$directory, '/');

        if (is_dir($path)) {
            rmdir($path);
        }
    }
}

describe('ModuleAssetManager::registerModuleViewPaths()', function (): void {
    it('puts a theme resources/views ahead of the theme root', function (): void {
        [$modulePath, $paths] = moduleWithViewPaths('theme', ['resources/views', 'views']);

        expect($paths)->toBe(['resources/views', 'views', $modulePath]);

        removeModule($modulePath);
    });

    it('keeps resources/views ahead of views for a plain module', function (): void {
        [$modulePath, $paths] = moduleWithViewPaths('plugin', ['resources/views', 'views']);

        expect($paths)->toBe(['resources/views', 'views']);

        removeModule($modulePath);
    });

    it('registers only the directories that exist', function (): void {
        [$modulePath, $paths] = moduleWithViewPaths('plugin', ['resources/views']);

        expect($paths)->toBe(['resources/views']);

        removeModule($modulePath);
    });
});
