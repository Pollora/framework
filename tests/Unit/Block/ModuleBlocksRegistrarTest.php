<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Nwidart\Modules\Contracts\RepositoryInterface;
use Nwidart\Modules\Module;
use Pollora\Asset\Application\Services\AssetManager;
use Pollora\Asset\Infrastructure\Repositories\AssetContainer;
use Pollora\Block\Domain\Contracts\BlockRegistrarInterface;
use Pollora\Block\Infrastructure\Services\ModuleBlocksRegistrar;
use Pollora\Modules\Infrastructure\Services\ModuleAssetManager;

beforeEach(function (): void {
    $this->root = sys_get_temp_dir().'/pollora-module-blocks-'.uniqid();
    mkdir($this->root, 0755, true);

    // public_path() resolves through the global container
    $this->app = Mockery::mock(Container::class)->makePartial();
    $this->app->shouldReceive('publicPath')->andReturnUsing(fn ($path = ''): string => '/public'.($path ? '/'.$path : ''));
    Container::setInstance($this->app);
    $this->assetManager = Mockery::mock(AssetManager::class);
    $this->assetManager->shouldReceive('addContainer')->byDefault();
    $this->assetManager->shouldReceive('getContainer')->andReturnNull()->byDefault();
    $this->app->instance(AssetManager::class, $this->assetManager);

    $this->moduleAssets = new ModuleAssetManager($this->app);
    $this->registrar = Mockery::mock(BlockRegistrarInterface::class);
    $this->registered = [];
    $this->registrar->shouldReceive('registerDirectory')->andReturnUsing(function (string $directory, string $containerName): void {
        $this->registered[$containerName] = $directory;
    });

    $this->moduleBlocks = fn (): ModuleBlocksRegistrar => new ModuleBlocksRegistrar($this->app, $this->registrar, $this->moduleAssets);
});

afterEach(function (): void {
    Container::setInstance(new Container);

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($this->root, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($files as $file) {
        $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
    }

    rmdir($this->root);
});

function makeModuleRoot(string $root, string $name, ?string $blocksDirectory = 'resources/views/blocks'): string
{
    $path = $root.'/'.$name;
    mkdir($blocksDirectory === null ? $path : $path.'/'.$blocksDirectory, 0755, true);

    return $path;
}

function bindLaravelModules(Container $app, array $modules): void
{
    $repository = Mockery::mock(RepositoryInterface::class);
    $repository->shouldReceive('allEnabled')->andReturn($modules);
    $app->instance('modules', $repository);
}

function mockLaravelModule(string $name, string $path): Module
{
    $module = Mockery::mock(Module::class);
    $module->shouldReceive('getName')->andReturn($name);
    $module->shouldReceive('getPath')->andReturn($path);

    return $module;
}

describe('ModuleBlocksRegistrar', function (): void {
    it('registers the blocks of every theme and plugin whose assets were set up', function (): void {
        $theme = makeModuleRoot($this->root, 'theme');
        $plugin = makeModuleRoot($this->root, 'plugin');
        $this->moduleAssets->setupModuleAssetContainer('theme', $theme, 'theme', 'theme');
        $this->moduleAssets->setupModuleAssetContainer('acme', $plugin, 'plugin', 'acme');

        ($this->moduleBlocks)()->registerAll();

        expect($this->registered)->toBe([
            'theme' => $theme.'/resources/views/blocks',
            'plugin.acme' => $plugin.'/resources/views/blocks',
        ]);
    });

    it('registers a module that only has the former resources/blocks directory', function (): void {
        // BlockRegistrar scans both locations from either one, and reports the former.
        $plugin = makeModuleRoot($this->root, 'plugin', 'resources/blocks');
        $this->moduleAssets->setupModuleAssetContainer('acme', $plugin, 'plugin', 'acme');

        ($this->moduleBlocks)()->registerAll();

        expect($this->registered)->toBe(['plugin.acme' => $plugin.'/resources/views/blocks']);
    });

    it('leaves alone a module without blocks', function (): void {
        $plugin = makeModuleRoot($this->root, 'plugin', null);
        $this->moduleAssets->setupModuleAssetContainer('acme', $plugin, 'plugin', 'acme');

        ($this->moduleBlocks)()->registerAll();

        expect($this->registered)->toBe([]);
    });

    it('creates the asset container of a Laravel module that ships blocks, and no view path', function (): void {
        // A Laravel module declares its container in a provider of its own,
        // which boots too late for init.
        $module = makeModuleRoot($this->root, 'BlocksDemo');
        bindLaravelModules($this->app, [mockLaravelModule('BlocksDemo', $module)]);
        $this->app->instance('view', Mockery::mock()->shouldNotReceive('getFinder')->getMock());

        $this->assetManager->shouldReceive('addContainer')->once()->with('module.blocks-demo', Mockery::on(
            fn (array $config): bool => $config['build_directory'] === 'build/module/blocks-demo'
                && $config['module_path'] === $module
        ));

        ($this->moduleBlocks)()->registerAll();

        expect($this->registered)->toBe(['module.blocks-demo' => $module.'/resources/views/blocks']);
    });

    it('keeps the asset container a Laravel module declared itself', function (): void {
        $module = makeModuleRoot($this->root, 'BlocksDemo');
        bindLaravelModules($this->app, [mockLaravelModule('BlocksDemo', $module)]);

        $this->assetManager->shouldReceive('getContainer')->with('module.blocks-demo')->andReturn(new AssetContainer('module.blocks-demo'));
        $this->assetManager->shouldNotReceive('addContainer');

        ($this->moduleBlocks)()->registerAll();

        expect($this->registered)->toBe(['module.blocks-demo' => $module.'/resources/views/blocks']);
    });

    it('skips a Laravel module without blocks', function (): void {
        $module = makeModuleRoot($this->root, 'Shop', null);
        bindLaravelModules($this->app, [mockLaravelModule('Shop', $module)]);
        $this->assetManager->shouldNotReceive('addContainer');

        ($this->moduleBlocks)()->registerAll();

        expect($this->registered)->toBe([]);
    });
});
