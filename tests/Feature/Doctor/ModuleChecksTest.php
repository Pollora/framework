<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Filesystem\Filesystem;
use Pollora\Doctor\Domain\Enums\RunContext;
use Pollora\Doctor\Infrastructure\Checks\AssetBuildCheck;
use Pollora\Doctor\Infrastructure\Checks\BlockRegistrationCheck;
use Pollora\Doctor\Infrastructure\Checks\DevelopmentCachesCheck;
use Pollora\Doctor\Infrastructure\Checks\LegacyBlocksDirectoryCheck;
use Pollora\Doctor\Infrastructure\Checks\SymlinkedDirectoryCheck;
use Pollora\Doctor\Infrastructure\Checks\TemplatePlaceholdersCheck;
use Pollora\Doctor\Infrastructure\Support\ProjectModule;
use Pollora\Doctor\Infrastructure\Support\ProjectModules;
use Pollora\Theme\Application\Services\ThemeAvailability;
use Pollora\Theme\Domain\Contracts\ThemeModuleInterface;
use Pollora\Theme\Domain\Contracts\ThemeRegistrarInterface;

/**
 * The checks that look at everything a project builds: the active theme, the Pollora
 * plugins and the Laravel modules — each failure replayed for each kind.
 */

// Declared only for these tests when WordPress is absent: the registry the block check reads.
if (! class_exists('WP_Block_Type_Registry')) {
    eval('final class WP_Block_Type_Registry {
        public static array $registered = [];
        public static function get_instance(): self { return new self; }
        public function is_registered(string $name): bool { return in_array($name, self::$registered, true); }
    }');
}

if (! function_exists('putModuleFile')) {
    function putModuleFile(string $path, string $contents): void
    {
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        file_put_contents($path, $contents);
    }
}

beforeEach(function (): void {
    $this->root = sys_get_temp_dir().'/pollora-doctor-modules-'.uniqid();
    mkdir($this->root.'/public', 0777, true);
    $this->previousBasePath = app()->basePath();
    app()->setBasePath($this->root);
    app()->usePublicPath($this->root.'/public');

    $module = fn (string $type, string $name, string $root): ProjectModule => new ProjectModule(
        $type, $name, $root, $this->root.'/public/'.$name.'.hot', 'build/'.$type.'/'.$name,
    );

    $this->theme = $module('theme', 'journal', $this->root.'/themes/journal');
    $this->plugin = $module('plugin', 'acme-forms', $this->root.'/public/content/plugins/acme-forms');
    $this->module = $module('module', 'blocks-demo', $this->root.'/Modules/BlocksDemo');

    foreach ([$this->theme, $this->plugin, $this->module] as $each) {
        mkdir($each->root, 0777, true);
    }

    $this->modules = Mockery::mock(ProjectModules::class);
    $this->modules->shouldReceive('all')->andReturnUsing(fn (): array => [$this->theme, $this->plugin, $this->module]);
});

afterEach(function (): void {
    app()->setBasePath($this->previousBasePath);
    (new Filesystem)->deleteDirectory($this->root);
});

describe('Theme, plugin and module builds', function (): void {
    function availability(bool $themeMissing): ThemeAvailability
    {
        $registrar = Mockery::mock(ThemeRegistrarInterface::class);
        $registrar->shouldReceive('getActiveTheme')->andReturn($themeMissing ? null : Mockery::mock(ThemeModuleInterface::class));
        $container = new Container;
        $container->instance(ThemeRegistrarInterface::class, $registrar);

        return new ThemeAvailability($container);
    }

    it('fails when no theme is installed', function (): void {
        Brain\Monkey\Functions\when('get_stylesheet_directory')->justReturn('/nope');

        expect((new AssetBuildCheck(availability(true), $this->modules))->run(RunContext::Console)->status->value)->toBe('error');
    });

    it('names an unbuilt plugin, and a module built into another folder than Pollora reads', function (): void {
        putModuleFile($this->plugin->root.'/vite.config.js', '');
        putModuleFile($this->module->root.'/vite.config.js', '');
        putModuleFile($this->root.'/public/build/modules/blocks-demo/manifest.json', '{}');

        $result = (new AssetBuildCheck(availability(false), $this->modules))->run(RunContext::Console);

        expect($result->status->value)->toBe('error')
            ->and($result->details)->toBe([
                'plugin acme-forms: not built — no manifest at public/build/plugin/acme-forms/manifest.json',
                'module blocks-demo: built into public/build/modules/blocks-demo, but Pollora reads public/build/module/blocks-demo',
            ])
            ->and($result->fix)->toContain('cd public/content/plugins/acme-forms && npm install && npm run build');
    });

    it("recognises the Vite config of nwidart's stock module:make and points to pollora:module:frontend", function (): void {
        putModuleFile($this->module->root.'/vite.config.js', "laravel({\n    publicDirectory: '../../public',\n    buildDirectory: 'build-blocksdemo',\n})");

        $result = (new AssetBuildCheck(availability(false), $this->modules))->run(RunContext::Console);

        expect($result->details)->toContain("module blocks-demo: its vite.config.js is the one nwidart/laravel-modules' module:make writes, which builds into public/build-blocksdemo, where Pollora never looks")
            ->and($result->fix)->toContain('php artisan pollora:module:frontend BlocksDemo');
    });

    it('tells a stopped dev server from one the browser cannot reach', function (): void {
        putModuleFile($this->theme->root.'/vite.config.js', '');
        putModuleFile($this->theme->hotFile, 'https://site.test:5173');
        putModuleFile($this->plugin->root.'/vite.config.js', '');
        putModuleFile($this->plugin->hotFile, 'https://site.test:5174');

        // 502: a proxy with nothing behind it. 404: something answers, but not Vite.
        $probe = fn (string $url): int => str_contains($url, '5173') ? 502 : 404;
        $result = (new AssetBuildCheck(availability(false), $this->modules, $probe))->run(RunContext::Console);

        expect($result->details[0])->toContain('where no Vite dev server answers')
            ->and($result->details[1])->toContain("answers 404 for Vite's client")
            ->and($result->fix)->toContain('web_extra_exposed_ports');
    });

    it('passes built modules, and one whose dev server answers', function (): void {
        putModuleFile($this->theme->root.'/vite.config.js', '');
        putModuleFile($this->theme->hotFile, 'https://site.test:5173');
        putModuleFile($this->plugin->root.'/vite.config.js', '');
        putModuleFile($this->root.'/public/build/plugin/acme-forms/manifest.json', '{}');

        $result = (new AssetBuildCheck(availability(false), $this->modules, fn (): int => 200))->run(RunContext::Console);

        expect($result->status->value)->toBe('ok')
            ->and($result->summary)->toStartWith('2 Vite build(s)');
    });
});

describe('Symlinked directories', function (): void {
    it('fails for a plugin linked to a directory of another name', function (): void {
        (new Filesystem)->deleteDirectory($this->plugin->root);
        mkdir($this->root.'/repos/plugin-acme-forms', 0777, true);
        symlink($this->root.'/repos/plugin-acme-forms', $this->plugin->root);

        $result = (new SymlinkedDirectoryCheck($this->modules))->run(RunContext::Console);

        expect($result->status->value)->toBe('error')
            ->and($result->details)->toHaveCount(1)
            ->and($result->details[0])->toStartWith('plugin acme-forms:');
    });

    it('passes real directories', function (): void {
        expect((new SymlinkedDirectoryCheck($this->modules))->run(RunContext::Console)->status->value)->toBe('ok');
    });
});

describe('Template placeholders', function (): void {
    it('names a copied theme and a copied plugin, stubs included, and says how to generate each', function (): void {
        putModuleFile($this->theme->root.'/app/Providers/AssetServiceProvider.php', "<?php\nnamespace %theme_namespace%\\Providers;\n");
        putModuleFile($this->theme->root.'/node_modules/pkg/index.js', '%theme_name%');
        putModuleFile($this->plugin->root.'/app/Providers/PluginServiceProvider.stub', '<?php');
        putModuleFile($this->plugin->root.'/%plugin_name%.php', '<?php');
        putModuleFile($this->module->root.'/module.json', '{"name": "%plugin_name%"}');

        $result = (new TemplatePlaceholdersCheck($this->modules))->run(RunContext::Console);

        expect($result->status->value)->toBe('error')
            ->and($result->details)->toBe([
                'theme journal: app/Providers/AssetServiceProvider.php',
                'plugin acme-forms: %plugin_name%.php, app/Providers/PluginServiceProvider.stub',
            ])
            ->and($result->fix)->toContain('pollora:make:theme journal')
            ->and($result->fix)->toContain('pollora:make:plugin acme-forms');
    });
});

describe('Blocks in the legacy folder', function (): void {
    it('warns about blocks still in resources/blocks, for each kind', function (): void {
        putModuleFile($this->plugin->root.'/resources/blocks/accordion/block.json', '{"name": "acme/accordion"}');
        putModuleFile($this->module->root.'/resources/blocks/testimonial/block.json', '{"name": "demo/testimonial"}');
        putModuleFile($this->theme->root.'/resources/views/blocks/hero/block.json', '{"name": "journal/hero"}');

        $result = (new LegacyBlocksDirectoryCheck($this->modules))->run(RunContext::Console);

        expect($result->status->value)->toBe('warning')
            ->and($result->details)->toBe([
                'plugin acme-forms: 1 block(s) in public/content/plugins/acme-forms/resources/blocks',
                'module blocks-demo: 1 block(s) in Modules/BlocksDemo/resources/blocks',
            ]);
    });
});

describe('Blocks registered', function (): void {
    beforeEach(function (): void {
        putModuleFile($this->theme->root.'/resources/views/blocks/hero/block.json', '{"name": "journal/hero"}');
        putModuleFile($this->plugin->root.'/resources/views/blocks/form/block.json', '{"name": "acme/form"}');
        putModuleFile($this->module->root.'/resources/blocks/testimonial/block.json', '{"name": "demo/testimonial"}');
    });

    it('names the blocks of the theme, the plugins and the modules missing from the request', function (): void {
        WP_Block_Type_Registry::$registered = ['journal/hero'];

        $result = (new BlockRegistrationCheck($this->modules))->run(RunContext::Http);

        expect($result->status->value)->toBe('error')
            ->and($result->details)->toBe([
                'plugin acme-forms: acme/form (resources/views/blocks/form/block.json)',
                'module blocks-demo: demo/testimonial (resources/blocks/testimonial/block.json)',
            ]);
    });

    it('passes when every block is registered', function (): void {
        WP_Block_Type_Registry::$registered = ['journal/hero', 'acme/form', 'demo/testimonial'];

        expect((new BlockRegistrationCheck($this->modules))->run(RunContext::Http)->summary)->toBe('3 block(s) registered.');
    });
});

describe('Configuration and route caches', function (): void {
    it('warns about a configuration cached outside production', function (): void {
        app()->detectEnvironment(fn (): string => 'local');
        // What Laravel records at boot when it loads the configuration from its cache.
        app()->instance('config_loaded_from_cache', true);

        $result = (new DevelopmentCachesCheck(app()))->run(RunContext::Console);

        expect($result->status->value)->toBe('warning')
            ->and($result->fix)->toBe('php artisan optimize:clear');
    });

    it('accepts caches in production', function (): void {
        app()->detectEnvironment(fn (): string => 'production');
        app()->instance('config_loaded_from_cache', true);

        expect((new DevelopmentCachesCheck(app()))->run(RunContext::Console)->status->value)->toBe('ok');
    });
});
