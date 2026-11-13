<?php

declare(strict_types=1);

use Brain\Monkey\Functions;
use Illuminate\Filesystem\Filesystem;
use Nwidart\Modules\Module;
use Pollora\Asset\Application\Services\AssetManager;
use Pollora\Asset\Infrastructure\Repositories\AssetContainer;
use Pollora\Doctor\Infrastructure\Support\ActiveTheme;
use Pollora\Doctor\Infrastructure\Support\ProjectModule;
use Pollora\Doctor\Infrastructure\Support\ProjectModules;
use Pollora\Modules\Infrastructure\Services\ModuleAssetManager;
use Pollora\Plugin\Application\Services\PluginRegistrar;

beforeEach(function (): void {
    $this->project = sys_get_temp_dir().'/pollora-doctor-support-'.uniqid();
    mkdir($this->project.'/public', 0777, true);
    $this->previousBasePath = app()->basePath();
    app()->setBasePath($this->project);
    app()->usePublicPath($this->project.'/public');

    $this->put = function (string $path, string $contents = 'x'): void {
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        file_put_contents($path, $contents);
    };
});

afterEach(function (): void {
    app()->setBasePath($this->previousBasePath);
    (new Filesystem)->deleteDirectory($this->project);
});

describe('ActiveTheme', function (): void {
    it('reports the theme directory without a trailing slash, and its slug', function (): void {
        Functions\when('get_stylesheet_directory')->justReturn('/srv/themes/journal/');
        Functions\when('get_stylesheet')->justReturn('journal');

        expect(ActiveTheme::directory())->toBe('/srv/themes/journal')
            ->and(ActiveTheme::slug())->toBe('journal');
    });

    it('has no directory when WordPress reports an empty one', function (): void {
        Functions\when('get_stylesheet_directory')->justReturn('');

        expect(ActiveTheme::directory())->toBeNull()
            ->and(ActiveTheme::relativeDirectory())->toBeNull();
    });

    it('gives the theme directory relative to the project, and as is outside of it', function (): void {
        Functions\when('get_stylesheet_directory')->justReturn($this->project.'/themes/journal');
        expect(ActiveTheme::relativeDirectory())->toBe('themes/journal');

        Functions\when('get_stylesheet_directory')->justReturn('/elsewhere/journal');
        expect(ActiveTheme::relativeDirectory())->toBe('/elsewhere/journal');
    });

    it('lists files by extension, sorted, leaving dependencies and builds out', function (): void {
        $theme = $this->project.'/themes/journal';
        ($this->put)($theme.'/patterns/b.php');
        ($this->put)($theme.'/patterns/a.PHP');
        ($this->put)($theme.'/patterns/sub/c.php');
        ($this->put)($theme.'/patterns/readme.md');
        ($this->put)($theme.'/patterns/node_modules/dep.php');
        ($this->put)($theme.'/patterns/vendor/dep.php');
        Functions\when('get_stylesheet_directory')->justReturn($theme);

        expect(ActiveTheme::files('patterns', ['php']))->toBe(['patterns/a.PHP', 'patterns/b.php', 'patterns/sub/c.php'])
            ->and(ActiveTheme::files('missing', ['php']))->toBe([]);
    });

    it('stops listing at the limit', function (): void {
        $theme = $this->project.'/themes/journal';
        foreach (['a', 'b', 'c'] as $name) {
            ($this->put)($theme.'/patterns/'.$name.'.php');
        }

        Functions\when('get_stylesheet_directory')->justReturn($theme);

        expect(ActiveTheme::files('patterns', ['php'], 2))->toHaveCount(2);
    });

    it('requires both a Title and a Slug in the header of a pattern', function (string $header, bool $valid): void {
        $file = $this->project.'/pattern.php';
        file_put_contents($file, $header);

        expect(ActiveTheme::patternHasHeader($file))->toBe($valid);
    })->with([
        'docblock with both' => ["<?php\n/**\n * Title: Hero\n * Slug: journal/hero\n */", true],
        'case-insensitive keys' => ["<?php\n/**\n * title: Hero\n * slug: journal/hero\n */", true],
        'title only' => ["<?php\n/**\n * Title: Hero\n */", false],
        'slug only' => ["<?php\n/**\n * Slug: journal/hero\n */", false],
        'no header' => ["<?php\necho 'x';", false],
    ]);
});

describe('ProjectModule', function (): void {
    it('is labelled by its type and name, and its manifest sits in the public build directory', function (): void {
        $module = new ProjectModule('plugin', 'acme-forms', '/srv/plugins/acme-forms', '/tmp/hot', '/build/acme/', '/manifest.json');

        expect($module->label())->toBe('plugin acme-forms')
            ->and($module->manifest())->toBe(public_path('build/acme/manifest.json'));
    });

    it('gives its root relative to the project when it is inside it', function (): void {
        expect((new ProjectModule('theme', 't', $this->project.'/themes/t', 'h', 'b'))->relativeRoot())->toBe('themes/t')
            ->and((new ProjectModule('theme', 't', '/elsewhere/t', 'h', 'b'))->relativeRoot())->toBe('/elsewhere/t');
    });

    it('lists its files by extension, relative to its root, leaving dependencies out', function (): void {
        $root = $this->project.'/modules/shop';
        ($this->put)($root.'/resources/a.js');
        ($this->put)($root.'/resources/deep/b.JS');
        ($this->put)($root.'/resources/node_modules/c.js');
        ($this->put)($root.'/resources/.git/d.js');
        ($this->put)($root.'/resources/e.css');

        $module = new ProjectModule('module', 'shop', $root, 'h', 'b');

        expect($module->files('resources', ['js']))->toBe(['resources/a.js', 'resources/deep/b.JS'])
            ->and($module->files('resources', ['js'], 1))->toHaveCount(1)
            ->and($module->files('nothing', ['js']))->toBe([]);
    });
});

describe('ProjectModules', function (): void {
    beforeEach(function (): void {
        $this->assets = Mockery::mock(ModuleAssetManager::class);
        $this->assets->shouldReceive('expectedAssetConfiguration')->andReturnUsing(
            fn (string $name, string $type): array => [
                'container' => $type.'.'.$name,
                'hot_file' => '/hot/'.$type.'-'.$name,
                'build_directory' => 'build/'.$type.'/'.$name,
                'manifest_path' => 'manifest.json',
            ]
        );
        $this->app->instance(ModuleAssetManager::class, $this->assets);
    });

    it('has the active theme, with the build paths Pollora expects for it', function (): void {
        $theme = $this->project.'/themes/Journal';
        mkdir($theme, 0777, true);
        Functions\when('get_stylesheet_directory')->justReturn($theme);
        Functions\when('get_stylesheet')->justReturn('Journal');

        $themes = (new ProjectModules($this->app))->theme();

        expect($themes)->toHaveCount(1)
            ->and($themes[0]->type)->toBe('theme')
            ->and($themes[0]->name)->toBe('Journal')
            ->and($themes[0]->root)->toBe($theme)
            ->and($themes[0]->hotFile)->toBe('/hot/theme-journal')
            ->and($themes[0]->buildDirectory)->toBe('build/theme/journal');
    });

    it('has no theme when its directory does not exist', function (): void {
        Functions\when('get_stylesheet_directory')->justReturn($this->project.'/nowhere');
        Functions\when('get_stylesheet')->justReturn('journal');

        expect((new ProjectModules($this->app))->theme())->toBe([]);
    });

    it('has the plugins registered with pollora_register()', function (): void {
        $plugin = new class
        {
            public function getLowerName(): string
            {
                return 'acme-forms';
            }

            public function getPath(): string
            {
                return '/srv/plugins/acme-forms/';
            }
        };
        $registrar = Mockery::mock(PluginRegistrar::class);
        $registrar->shouldReceive('getRegisteredPlugins')->andReturn([$plugin]);
        $this->app->instance(PluginRegistrar::class, $registrar);

        $plugins = (new ProjectModules($this->app))->plugins();

        expect($plugins)->toHaveCount(1)
            ->and($plugins[0]->type)->toBe('plugin')
            ->and($plugins[0]->name)->toBe('acme-forms')
            ->and($plugins[0]->root)->toBe('/srv/plugins/acme-forms')
            ->and($plugins[0]->buildDirectory)->toBe('build/plugin/acme-forms');
    });

    it('has no plugin when the registrar is not bound', function (): void {
        expect((new ProjectModules($this->app))->plugins())->toBe([]);
    });

    it('has the enabled Laravel modules, with a kebab-case asset name', function (): void {
        $module = Mockery::mock(Module::class);
        $module->shouldReceive('getName')->andReturn('BlocksDemo');
        $module->shouldReceive('getPath')->andReturn('/srv/Modules/BlocksDemo/');
        $repository = Mockery::mock();
        $repository->shouldReceive('allEnabled')->andReturn([$module]);
        $this->app->instance('modules', $repository);

        $modules = (new ProjectModules($this->app))->modules();

        expect($modules)->toHaveCount(1)
            ->and($modules[0]->type)->toBe('module')
            ->and($modules[0]->name)->toBe('BlocksDemo')
            ->and($modules[0]->root)->toBe('/srv/Modules/BlocksDemo')
            ->and($modules[0]->buildDirectory)->toBe('build/module/blocks-demo');
    });

    it('has no module when the module repository is unavailable or fails', function (): void {
        expect((new ProjectModules($this->app))->modules())->toBe([]);

        $repository = Mockery::mock();
        $repository->shouldReceive('allEnabled')->andThrow(new RuntimeException('broken'));
        $this->app->instance('modules', $repository);

        expect((new ProjectModules($this->app))->modules())->toBe([]);
    });

    it('prefers the paths of the asset container when one is set up', function (): void {
        $plugin = new class
        {
            public function getLowerName(): string
            {
                return 'acme-forms';
            }

            public function getPath(): string
            {
                return '/srv/plugins/acme-forms';
            }
        };
        $registrar = Mockery::mock(PluginRegistrar::class);
        $registrar->shouldReceive('getRegisteredPlugins')->andReturn([$plugin]);
        $this->app->instance(PluginRegistrar::class, $registrar);

        $container = Mockery::mock(AssetContainer::class);
        $container->shouldReceive('getHotFile')->andReturn('/custom/hot');
        $container->shouldReceive('getBuildDirectory')->andReturn('custom/build');
        $container->shouldReceive('getManifestPath')->andReturn('custom/manifest.json');
        $manager = Mockery::mock(AssetManager::class);
        $manager->shouldReceive('getContainer')->with('plugin.acme-forms')->andReturn($container);
        $this->app->instance(AssetManager::class, $manager);

        $plugin = (new ProjectModules($this->app))->plugins()[0];

        expect($plugin->hotFile)->toBe('/custom/hot')
            ->and($plugin->buildDirectory)->toBe('custom/build')
            ->and($plugin->manifestPath)->toBe('custom/manifest.json');
    });

    it('lists the theme, the plugins and the modules together, in that order', function (): void {
        $theme = $this->project.'/themes/journal';
        mkdir($theme, 0777, true);
        Functions\when('get_stylesheet_directory')->justReturn($theme);
        Functions\when('get_stylesheet')->justReturn('journal');

        $module = Mockery::mock(Module::class);
        $module->shouldReceive('getName')->andReturn('Shop');
        $module->shouldReceive('getPath')->andReturn('/srv/Modules/Shop');
        $repository = Mockery::mock();
        $repository->shouldReceive('allEnabled')->andReturn([$module]);
        $this->app->instance('modules', $repository);

        $all = (new ProjectModules($this->app))->all();

        expect(array_map(fn (ProjectModule $m): string => $m->label(), $all))->toBe(['theme journal', 'module Shop']);
    });
});
