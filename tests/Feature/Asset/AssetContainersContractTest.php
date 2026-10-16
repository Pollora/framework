<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Pollora\Asset\Application\Services\AssetManager;
use Pollora\Asset\Application\Services\AssetRetrievalService;
use Pollora\Asset\Application\Services\MissingBuilds;
use Pollora\Asset\Domain\Contracts\ViteManagerFactory;
use Pollora\Asset\Infrastructure\Laravel\LaravelAssetFileResolver;
use Pollora\Asset\Infrastructure\Laravel\LaravelViteManagerFactory;
use Pollora\Asset\Infrastructure\Repositories\AssetContainer;
use Pollora\Asset\Infrastructure\Services\AssetFile;
use Pollora\Asset\Infrastructure\Services\RootAssetManager;
use Pollora\Asset\Infrastructure\Services\ViteManager;
use Pollora\Block\Infrastructure\Services\BlockRegistrar;
use Pollora\Hook\Domain\Contract\Filter as HookFilter;
use Pollora\Modules\Infrastructure\Services\ModuleAssetManager;

/*
 * The contract between the asset containers of a Pollora application and
 * the assets they resolve, fixed before pollora/assets leaves the framework.
 *
 * Everything is real: the containers are the ones the module manager, the
 * root manager and the block registrar create, resolved by the real
 * ViteManager against manifests and hot files written under public/.
 * The same URLs come out of a classic WordPress or a Bedrock, whatever the
 * Vite implementation that resolves them.
 */

beforeEach(function (): void {
    $this->suffix = uniqid();
    $this->names = [
        'theme' => 'apiary-'.$this->suffix,
        'plugin' => 'acme-'.$this->suffix,
        'module' => 'crm-'.$this->suffix,
    ];

    // The manager AssetFile resolves through the container
    $this->assetManager = new AssetManager(Mockery::mock(AssetRetrievalService::class));
    $this->app->instance(AssetManager::class, $this->assetManager);
    $this->app->singleton(MissingBuilds::class);
    $this->app->singleton(ViteManagerFactory::class, LaravelViteManagerFactory::class);
    AssetFile::resolveUsing(Closure::fromCallable(new LaravelAssetFileResolver($this->app)));

    $this->modules = new ModuleAssetManager($this->app);
    $this->modules->setupModuleAssetContainer($this->names['theme'], '/themes/apiary', 'theme');
    $this->modules->setupModuleAssetContainer($this->names['plugin'], '/plugins/acme', 'plugin', 'acme');
    $this->modules->setupModuleAssetContainer($this->names['module'], '/modules/Crm', 'module', 'crm');

    $this->hotFiles = [];
    $this->builds = [];

    /** Write the production build of a container: its manifest */
    $this->build = function (string $type, string $name, array $manifest): void {
        $directory = public_path('build/'.$type.'/'.$name);
        (new Filesystem)->ensureDirectoryExists($directory);
        file_put_contents($directory.'/manifest.json', json_encode($manifest));
        $this->builds[] = public_path('build/'.$type);
    };

    /** Start the dev server of a container: its hot file */
    $this->serve = function (string $name, string $url): void {
        $this->hotFiles[] = $file = public_path($name.'.hot');
        file_put_contents($file, $url);
    };
});

afterEach(function (): void {
    AssetFile::resolveUsing(null);

    foreach (array_unique($this->builds) as $directory) {
        foreach ($this->names as $name) {
            (new Filesystem)->deleteDirectory($directory.'/'.$name);
        }
    }

    foreach ($this->hotFiles as $file) {
        @unlink($file);
    }
});

describe('the containers of a theme, a plugin and a module', function (): void {
    it('name each one after its kind and its slug, and keep the layout @pollora/vite-config builds to', function (): void {
        $theme = $this->assetManager->getContainer('theme');
        $plugin = $this->assetManager->getContainer('plugin.acme');
        $module = $this->assetManager->getContainer('module.crm');

        expect($theme)->toBeInstanceOf(AssetContainer::class)
            ->and([$theme->getHotFile(), $theme->getBuildDirectory(), $theme->getManifestPath(), $theme->getBasePath()])
            ->toBe([public_path($this->names['theme'].'.hot'), 'build/theme/'.$this->names['theme'], 'manifest.json', 'resources/assets/'])
            ->and([$plugin->getHotFile(), $plugin->getBuildDirectory(), $plugin->getManifestPath(), $plugin->getBasePath()])
            ->toBe([public_path($this->names['plugin'].'.hot'), 'build/plugin/'.$this->names['plugin'], 'manifest.json', 'resources/assets/'])
            ->and([$module->getHotFile(), $module->getBuildDirectory(), $module->getManifestPath(), $module->getBasePath()])
            ->toBe([public_path($this->names['module'].'.hot'), 'build/module/'.$this->names['module'], 'manifest.json', 'resources/assets/'])
            ->and(array_keys($this->assetManager->containers()))->toBe(['theme', 'plugin.acme', 'module.crm']);
    });

    it('are the ones pollora:doctor expects', function (): void {
        expect($this->modules->expectedAssetConfiguration($this->names['plugin'], 'plugin', 'acme'))->toBe([
            'container' => 'plugin.acme',
            'hot_file' => public_path($this->names['plugin'].'.hot'),
            'build_directory' => 'build/plugin/'.$this->names['plugin'],
            'manifest_path' => 'manifest.json',
        ]);
    });
});

describe('the root container', function (): void {
    it('is the default one, on public/hot and public/build', function (): void {
        (new RootAssetManager($this->assetManager))->registerRootAssets();

        $root = $this->assetManager->getDefaultContainer();

        expect($root->getName())->toBe('root')
            ->and([$root->getHotFile(), $root->getBuildDirectory(), $root->getManifestPath(), $root->getBasePath()])
            ->toBe([public_path('hot'), 'build', 'manifest.json', ''])
            ->and($this->assetManager->getContainer('root'))->toBe($root);
    });
});

describe('the blocks container of a theme, a plugin or a module', function (): void {
    it('shares the hot file, the build and the manifest of its parent, with no base path', function (): void {
        $registrar = new class($this->assetManager, Mockery::mock(HookFilter::class)) extends BlockRegistrar
        {
            public function blocksManager(string $parent): ?ViteManager
            {
                return $this->getBlocksViteManager($parent);
            }
        };

        $manager = $registrar->blocksManager('plugin.acme');
        $parent = $this->assetManager->getContainer('plugin.acme');
        $blocks = $manager->container();

        expect($blocks->getName())->toBe('plugin.acme.blocks')
            ->and([$blocks->getHotFile(), $blocks->getBuildDirectory(), $blocks->getManifestPath(), $blocks->getBasePath()])
            ->toBe([$parent->getHotFile(), $parent->getBuildDirectory(), $parent->getManifestPath(), ''])
            ->and($this->assetManager->getContainer('plugin.acme.blocks'))->toBe($blocks)
            ->and($registrar->blocksManager('plugin.nothing'))->toBeNull();
    });
});

describe('built for production', function (): void {
    beforeEach(function (): void {
        ($this->build)('theme', $this->names['theme'], [
            'resources/assets/app.js' => ['file' => 'assets/app-AAA.js', 'css' => ['assets/app-BBB.css']],
            'resources/assets/app.css' => ['file' => 'assets/app-CCC.css'],
            'resources/assets/images/logo.svg' => ['file' => 'assets/logo-DDD.svg'],
        ]);
        ($this->build)('plugin', $this->names['plugin'], [
            'resources/assets/admin.js' => ['file' => 'assets/admin-EEE.js'],
            'resources/views/blocks/card/index.jsx' => ['file' => 'assets/index-FFF.js', 'css' => ['assets/index-GGG.css']],
        ]);
        ($this->build)('module', $this->names['module'], [
            'resources/assets/app.js' => ['file' => 'assets/app-HHH.js'],
        ]);
    });

    it('gives each container the URLs of its own build', function (): void {
        $theme = new ViteManager($this->assetManager->getContainer('theme'));
        $plugin = new ViteManager($this->assetManager->getContainer('plugin.acme'));
        $module = new ViteManager($this->assetManager->getContainer('module.crm'));

        expect($theme->isRunningHot())->toBeFalse()
            ->and($theme->getAssetUrls(['app.js']))->toBe([
                'js' => [asset('build/theme/'.$this->names['theme'].'/assets/app-AAA.js')],
                'css' => [asset('build/theme/'.$this->names['theme'].'/assets/app-BBB.css')],
            ])
            ->and($theme->getAssetUrls(['app.css']))->toBe([
                'js' => [],
                'css' => [asset('build/theme/'.$this->names['theme'].'/assets/app-CCC.css')],
            ])
            ->and($plugin->getAssetUrls(['admin.js']))->toBe([
                'js' => [asset('build/plugin/'.$this->names['plugin'].'/assets/admin-EEE.js')],
                'css' => [],
            ])
            ->and($module->getAssetUrls(['app.js']))->toBe([
                'js' => [asset('build/module/'.$this->names['module'].'/assets/app-HHH.js')],
                'css' => [],
            ]);
    });

    it('resolves a file the way the manifest names it, under the base path of its container', function (): void {
        $theme = new ViteManager($this->assetManager->getContainer('theme'));

        expect($theme->asset('images/logo.svg'))->toBe(asset('build/theme/'.$this->names['theme'].'/assets/logo-DDD.svg'))
            ->and((string) (new AssetFile('images/logo.svg'))->from('theme'))
            ->toBe(asset('build/theme/'.$this->names['theme'].'/assets/logo-DDD.svg'));
    });

    it('resolves block entries from the blocks container, whose base path is empty', function (): void {
        $registrar = new class($this->assetManager, Mockery::mock(HookFilter::class)) extends BlockRegistrar
        {
            public function blocksManager(string $parent): ?ViteManager
            {
                return $this->getBlocksViteManager($parent);
            }
        };

        expect($registrar->blocksManager('plugin.acme')->getAssetUrls(['resources/views/blocks/card/index.jsx']))->toBe([
            'js' => [asset('build/plugin/'.$this->names['plugin'].'/assets/index-FFF.js')],
            'css' => [asset('build/plugin/'.$this->names['plugin'].'/assets/index-GGG.css')],
        ]);
    });

    it('gives nothing, and says so once, for a container whose build is missing', function (): void {
        $this->modules->setupModuleAssetContainer('nothing-'.$this->suffix, '/plugins/nothing', 'plugin', 'nothing');
        $unbuilt = new ViteManager($this->assetManager->getContainer('plugin.nothing'));

        expect($unbuilt->getAssetUrls(['admin.js']))->toBe(['js' => [], 'css' => []])
            ->and($unbuilt->isMissingBuild())->toBeTrue()
            ->and(array_keys(resolve(MissingBuilds::class)->all()))->toBe(['plugin.nothing']);
    });

    it('answers an empty string for an unknown container', function (): void {
        expect((string) (new AssetFile('images/logo.svg'))->from('plugin.unknown'))->toBe('');
    });
});

describe('served by the dev servers', function (): void {
    beforeEach(function (): void {
        // The three servers run at once: each hot file names its own origin and build path, as @pollora/vite-config writes them
        ($this->serve)($this->names['theme'], 'https://site.test:5173/build/theme/'.$this->names['theme']);
        ($this->serve)($this->names['plugin'], 'https://site.test:5174/build/plugin/'.$this->names['plugin']);
        // The module stays on its production build
        ($this->build)('module', $this->names['module'], [
            'resources/assets/app.js' => ['file' => 'assets/app-HHH.js'],
        ]);
    });

    it('sends each container to its own dev server, and leaves the one that is not served on its build', function (): void {
        $theme = new ViteManager($this->assetManager->getContainer('theme'));
        $plugin = new ViteManager($this->assetManager->getContainer('plugin.acme'));
        $module = new ViteManager($this->assetManager->getContainer('module.crm'));

        expect($theme->isRunningHot())->toBeTrue()
            ->and($plugin->isRunningHot())->toBeTrue()
            ->and($module->isRunningHot())->toBeFalse()
            ->and($theme->asset('app.js'))->toBe('https://site.test:5173/build/theme/'.$this->names['theme'].'/resources/assets/app.js')
            ->and($plugin->asset('admin.js'))->toBe('https://site.test:5174/build/plugin/'.$this->names['plugin'].'/resources/assets/admin.js')
            ->and($module->getAssetUrls(['app.js']))->toBe(['js' => [asset('build/module/'.$this->names['module'].'/assets/app-HHH.js')], 'css' => []]);
    });

    it('gives each served container the Vite client of its own server', function (): void {
        $theme = new ViteManager($this->assetManager->getContainer('theme'));
        $plugin = new ViteManager($this->assetManager->getContainer('plugin.acme'));
        $module = new ViteManager($this->assetManager->getContainer('module.crm'));

        expect($theme->clientUrl())->toBe('https://site.test:5173/build/theme/'.$this->names['theme'].'/@vite/client')
            ->and($plugin->clientUrl())->toBe('https://site.test:5174/build/plugin/'.$this->names['plugin'].'/@vite/client')
            ->and($module->clientUrl())->toBe('');
    });

    it('does not count a served container as missing its build', function (): void {
        $theme = new ViteManager($this->assetManager->getContainer('theme'));

        expect($theme->isMissingBuild())->toBeFalse()
            ->and(resolve(MissingBuilds::class)->all())->toBe([]);
    });

    it('resolves a theme file through the dev server of the theme', function (): void {
        expect((string) (new AssetFile('images/logo.svg'))->from('theme'))
            ->toBe('https://site.test:5173/build/theme/'.$this->names['theme'].'/resources/assets/images/logo.svg');
    });
});
