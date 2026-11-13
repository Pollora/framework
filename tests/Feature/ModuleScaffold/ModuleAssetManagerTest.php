<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Illuminate\View\Factory;
use Illuminate\View\FileViewFinder;
use Pollora\Asset\Application\Services\AssetManager;
use Pollora\Modules\Infrastructure\Services\ModuleAssetManager;
use Psr\Log\LoggerInterface;

/**
 * Bind a logger collecting error messages into the container.
 */
function loggedErrors(Illuminate\Contracts\Container\Container $app): object
{
    $logger = new class extends Psr\Log\AbstractLogger
    {
        /** @var list<string> */
        public array $messages = [];

        public function log($level, string|Stringable $message, array $context = []): void
        {
            $this->messages[] = (string) $message;
        }
    };
    $app->instance(LoggerInterface::class, $logger);

    return $logger;
}

beforeEach(function (): void {
    $this->module = sys_get_temp_dir().'/pollora-assetmgr-'.uniqid();
    File::ensureDirectoryExists($this->module);
});

afterEach(function (): void {
    File::deleteDirectory($this->module);
    Mockery::close();
});

describe('ModuleAssetManager asset containers', function (): void {
    it('registers a theme under the container "theme" with its build paths', function (): void {
        $manager = Mockery::mock(AssetManager::class);
        $manager->shouldReceive('addContainer')->once()->with('theme', [
            'hot_file' => public_path('nectar.hot'),
            'build_directory' => 'build/theme/nectar',
            'manifest_path' => 'manifest.json',
            'base_path' => 'resources/assets/',
            'module_path' => $this->module,
            'module_type' => 'theme',
        ]);
        $this->app->instance(AssetManager::class, $manager);
        $assets = new ModuleAssetManager($this->app);

        $assets->setupModuleAssets('nectar', $this->module, 'theme');

        expect($assets->getModuleRoots())->toBe(['theme' => $this->module]);
    });

    it('names plugin and other containers after their slug, or the module name', function (): void {
        $names = [];
        $manager = Mockery::mock(AssetManager::class);
        $manager->shouldReceive('addContainer')->andReturnUsing(function (string $name) use (&$names): void {
            $names[] = $name;
        });
        $this->app->instance(AssetManager::class, $manager);
        $assets = new ModuleAssetManager($this->app);

        $assets->setupModuleAssetContainer('Crm', $this->module, 'plugin', 'crm-slug');
        $assets->setupModuleAssetContainer('Seo', $this->module, 'plugin');
        $returned = $assets->setupModuleAssetContainer('Shop', $this->module, 'module', 'shop');

        expect($names)->toBe(['plugin.crm-slug', 'plugin.Seo', 'module.shop'])
            ->and($returned)->toBe('module.shop')
            ->and(array_keys($assets->getModuleRoots()))->toBe($names);
    });

    it('does nothing without an asset manager', function (): void {
        $assets = new ModuleAssetManager($this->app);

        expect($assets->setupModuleAssetContainer('Crm', $this->module, 'plugin'))->toBeNull()
            ->and($assets->getModuleRoots())->toBe([]);
    });

    it('logs an asset failure instead of breaking the boot', function (): void {
        $logged = loggedErrors($this->app);
        $manager = Mockery::mock(AssetManager::class);
        $manager->shouldReceive('addContainer')->andThrow(new RuntimeException('bad container'));
        $this->app->instance(AssetManager::class, $manager);

        (new ModuleAssetManager($this->app))->setupModuleAssets('Crm', $this->module, 'plugin');

        expect($logged->messages)->toHaveCount(1)
            ->and($logged->messages[0])->toContain('Crm', 'plugin');
    });

    it('tells the container and paths a module will get before it is set up', function (): void {
        $expected = (new ModuleAssetManager($this->app))->expectedAssetConfiguration('nectar', 'theme');

        expect($expected)->toBe([
            'container' => 'theme',
            'hot_file' => public_path('nectar.hot'),
            'build_directory' => 'build/theme/nectar',
            'manifest_path' => 'manifest.json',
        ])->and((new ModuleAssetManager($this->app))->expectedAssetConfiguration('Crm', 'plugin', 'crm')['container'])->toBe('plugin.crm');
    });
});

describe('ModuleAssetManager includes and directives', function (): void {
    it('includes the PHP files of app/inc in alphabetical order', function (): void {
        File::ensureDirectoryExists($this->module.'/app/inc/sub');
        $GLOBALS['pollora_inc_order'] = [];
        file_put_contents($this->module.'/app/inc/b.php', '<?php $GLOBALS["pollora_inc_order"][] = "b";');
        file_put_contents($this->module.'/app/inc/a.php', '<?php $GLOBALS["pollora_inc_order"][] = "a";');
        file_put_contents($this->module.'/app/inc/sub/c.php', '<?php $GLOBALS["pollora_inc_order"][] = "c";');
        file_put_contents($this->module.'/app/inc/skip.txt', '<?php $GLOBALS["pollora_inc_order"][] = "txt";');

        (new ModuleAssetManager($this->app))->loadModuleIncludes($this->module);

        expect($GLOBALS['pollora_inc_order'])->toBe(['a', 'b', 'c']);
        unset($GLOBALS['pollora_inc_order']);
    });

    it('has no include to load when app/inc does not exist', function (): void {
        (new ModuleAssetManager($this->app))->loadModuleIncludes($this->module);

        expect(is_dir($this->module.'/app/inc'))->toBeFalse();
    });

    it('registers the Blade directives a module declares', function (): void {
        File::ensureDirectoryExists($this->module.'/resources');
        file_put_contents($this->module.'/resources/directives.php', '<?php return ["pollora_cov_shout" => fn ($e) => "<?php echo strtoupper($e); ?>"];');

        (new ModuleAssetManager($this->app))->registerModuleBladeDirectives($this->module);

        expect(Blade::getCustomDirectives())->toHaveKey('pollora_cov_shout')
            ->and(Blade::compileString('@pollora_cov_shout("a")'))->toContain('strtoupper("a")');
    });

    it('ignores a directives file that does not return an array', function (): void {
        File::ensureDirectoryExists($this->module.'/resources');
        file_put_contents($this->module.'/resources/directives.php', '<?php return "nope";');
        $before = Blade::getCustomDirectives();

        (new ModuleAssetManager($this->app))->registerModuleBladeDirectives($this->module);

        expect(Blade::getCustomDirectives())->toBe($before);
    });

    it('logs a directives file that fails instead of breaking the boot', function (): void {
        File::ensureDirectoryExists($this->module.'/resources');
        file_put_contents($this->module.'/resources/directives.php', '<?php throw new RuntimeException("bad");');
        $logged = loggedErrors($this->app);

        (new ModuleAssetManager($this->app))->registerModuleBladeDirectives($this->module);

        expect($logged->messages)->toHaveCount(1)
            ->and($logged->messages[0])->toContain('Blade directives');
    });
});

describe('ModuleAssetManager view paths', function (): void {
    it('does nothing when no view factory is bound', function (): void {
        $app = new Illuminate\Container\Container;

        (new ModuleAssetManager($app))->registerModuleViewPaths($this->module, 'theme', 'demo');

        expect($app->bound('view'))->toBeFalse();
    });

    it('declares a view namespace for a module with a slug and views', function (): void {
        File::ensureDirectoryExists($this->module.'/resources/views');
        $factory = Mockery::mock(Factory::class);
        $factory->shouldReceive('getFinder')->andReturn(new FileViewFinder(new Filesystem, []));
        $namespaces = [];
        $factory->shouldReceive('addNamespace')->andReturnUsing(function (string $ns, string $path) use (&$namespaces, $factory) {
            $namespaces[$ns] = $path;

            return $factory;
        });
        $app = new Illuminate\Container\Container;
        $app->instance('view', $factory);

        (new ModuleAssetManager($app))->registerModuleViewPaths($this->module, 'plugin', 'demo');

        expect($namespaces)->toBe(['demo' => $this->module.'/resources/views']);
    });

    it('does not register a path twice', function (): void {
        File::ensureDirectoryExists($this->module.'/resources/views');
        $finder = new FileViewFinder(new Filesystem, []);
        $factory = Mockery::mock(Factory::class);
        $factory->shouldReceive('getFinder')->andReturn($finder);
        $app = new Illuminate\Container\Container;
        $app->instance('view', $factory);
        $assets = new ModuleAssetManager($app);

        $assets->registerModuleViewPaths($this->module, 'plugin');
        $assets->registerModuleViewPaths($this->module, 'plugin');

        expect($finder->getPaths())->toBe([$this->module.'/resources/views']);
    });

    it('logs a view factory failure instead of breaking the boot', function (): void {
        $factory = Mockery::mock(Factory::class);
        $factory->shouldReceive('getFinder')->andThrow(new RuntimeException('no finder'));
        $app = new Illuminate\Container\Container;
        $app->instance('view', $factory);
        $logged = loggedErrors($app);

        (new ModuleAssetManager($app))->registerModuleViewPaths($this->module, 'theme');

        expect($logged->messages)->toHaveCount(1)
            ->and($logged->messages[0])->toContain('view paths');
    });
});
