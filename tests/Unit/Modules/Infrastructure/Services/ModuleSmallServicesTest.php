<?php

declare(strict_types=1);

use Composer\Autoload\ClassLoader;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Route;
use Pollora\Asset\Application\Services\AssetManager;
use Pollora\Asset\Infrastructure\Repositories\AssetContainer;
use Pollora\Modules\Domain\Contracts\ModuleInterface;
use Pollora\Modules\Infrastructure\Services\LaravelModuleAssets;
use Pollora\Modules\Infrastructure\Services\LeanModuleMake;
use Pollora\Modules\Infrastructure\Services\ModuleAssetManager;
use Pollora\Modules\Infrastructure\Services\ModuleAutoloader;
use Pollora\Modules\Infrastructure\Services\ModuleRouteLoader;
use Pollora\Modules\Infrastructure\Services\ModuleScaffolderService;
use Pollora\Modules\Infrastructure\Services\ModuleTemplate;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;

function smallTempDir(): string
{
    $dir = sys_get_temp_dir().'/pollora-small-'.uniqid('', true);
    mkdir($dir, 0777, true);

    return $dir;
}

function smallRemove(string $dir): void
{
    if (! is_dir($dir)) {
        return;
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }

    rmdir($dir);
}

function smallLogger(): object
{
    return new class extends AbstractLogger
    {
        /** @var list<string> */
        public array $messages = [];

        public function log($level, string|Stringable $message, array $context = []): void
        {
            $this->messages[] = (string) $message;
        }
    };
}

function smallModule(string $path, string $name = 'Crm'): ModuleInterface
{
    $module = Mockery::mock(ModuleInterface::class);
    $module->shouldReceive('getPath')->andReturn($path);
    $module->shouldReceive('getName')->andReturn($name);
    $module->shouldReceive('getStudlyName')->andReturn($name);

    return $module;
}

beforeEach(function (): void {
    $this->dir = smallTempDir();
});

afterEach(function (): void {
    smallRemove($this->dir);
    Mockery::close();
});

describe('ModuleRouteLoader failures', function (): void {
    beforeEach(function (): void {
        Facade::setFacadeApplication(null);
        mkdir($this->dir.'/routes', 0777, true);
        $this->app = new Container;
        $this->logger = smallLogger();
        $this->app->instance(LoggerInterface::class, $this->logger);
    });

    it('logs the API routes it could not load and keeps loading the web routes', function (): void {
        file_put_contents($this->dir.'/routes/api.php', '<?php');
        file_put_contents($this->dir.'/routes/web.php', '<?php');
        Route::shouldReceive('prefix')->once()->with('api')->andThrow(new RuntimeException('api broke'));
        Route::shouldReceive('group')->once()->with([], $this->dir.'/routes/web.php');

        (new ModuleRouteLoader($this->app))->loadModuleRoutes(smallModule($this->dir));

        expect($this->logger->messages)->toBe(['Failed to load API routes: api broke']);
    });

    it('logs the web routes it could not load', function (): void {
        file_put_contents($this->dir.'/routes/web.php', '<?php');
        Route::shouldReceive('group')->once()->andThrow(new RuntimeException('web broke'));

        (new ModuleRouteLoader($this->app))->loadModuleRoutes(smallModule($this->dir));

        expect($this->logger->messages)->toBe(['Failed to load web routes: web broke']);
    });
});

describe('ModuleAutoloader sources and registration', function (): void {
    it('falls back to src/ when the module has no app/ directory', function (): void {
        mkdir($this->dir.'/src', 0777, true);
        $app = new Container;
        $loader = Mockery::mock(ClassLoader::class)->shouldIgnoreMissing();
        $app->instance(ModuleAutoloader::CLASS_LOADER, $loader);
        $registered = [];
        $loader->shouldReceive('addPsr4')->andReturnUsing(function (string $ns, string $path) use (&$registered): void {
            $registered[$ns] = $path;
        });

        (new ModuleAutoloader($app))->registerPlugin(smallModule($this->dir, 'Crm'));

        expect($registered)->toBe(['Plugin\\Crm\\' => $this->dir.'/src']);
    });

    it('registers nothing for a module without a source directory', function (): void {
        $app = new Container;
        $loader = Mockery::mock(ClassLoader::class)->shouldIgnoreMissing();
        $app->instance(ModuleAutoloader::CLASS_LOADER, $loader);
        $loader->shouldReceive('addPsr4')->never();
        $autoloader = new ModuleAutoloader($app);

        $autoloader->registerTheme(smallModule($this->dir));

        expect($autoloader)->toBeInstanceOf(ModuleAutoloader::class);
    });

    it('leaves a loader that Composer already lists alone, and registers any other', function (): void {
        $loader = new ClassLoader($this->dir.'/vendor-a');
        $app = new Container;
        $app->instance(ModuleAutoloader::CLASS_LOADER, $loader);

        $autoloader = new ModuleAutoloader($app);
        $before = count(spl_autoload_functions());

        $autoloader->register();
        $afterFirst = count(spl_autoload_functions());
        $autoloader->register();
        $afterSecond = count(spl_autoload_functions());
        $loader->unregister();

        expect($afterFirst)->toBe($before + 1)
            ->and($afterSecond)->toBe($afterFirst)
            ->and(count(spl_autoload_functions()))->toBe($before);
    });
});

describe('LaravelModuleAssets', function (): void {
    beforeEach(function (): void {
        $this->app = new Container;
        $this->moduleAssets = Mockery::mock(ModuleAssetManager::class);
        $this->assetManager = Mockery::mock(AssetManager::class);
    });

    it('does nothing without the nwidart repository or the asset manager', function (): void {
        $this->moduleAssets->shouldReceive('setupModuleAssetContainer')->never();
        $assets = new LaravelModuleAssets($this->app, $this->moduleAssets);

        $assets->setUp();

        $this->app->instance('modules', new class
        {
            public function allEnabled(): array
            {
                return [];
            }
        });
        $assets->setUp();

        expect($this->app->bound(AssetManager::class))->toBeFalse();
    });

    it('gives up quietly when the repository cannot list modules', function (): void {
        $this->app->instance('modules', new class
        {
            public function allEnabled(): array
            {
                throw new RuntimeException('no modules');
            }
        });
        $this->app->instance(AssetManager::class, $this->assetManager);
        $this->moduleAssets->shouldReceive('setupModuleAssetContainer')->never();

        (new LaravelModuleAssets($this->app, $this->moduleAssets))->setUp();

        expect($this->app->bound('modules'))->toBeTrue();
    });

    it('sets up a kebab-named container only for enabled modules that have a Vite build and none yet', function (): void {
        foreach (['Blog', 'ShopFront', 'Plain', 'Known'] as $name) {
            mkdir($this->dir.'/'.$name, 0777, true);
        }

        file_put_contents($this->dir.'/ShopFront/vite.config.ts', '');
        file_put_contents($this->dir.'/Known/vite.config.js', '');
        file_put_contents($this->dir.'/Blog/vite.config.mjs', '');
        $modules = array_map(fn (string $n): object => new readonly class($n, $this->dir.'/'.$n.'/')
        {
            public function __construct(private string $n, private string $p) {}

            public function getName(): string
            {
                return $this->n;
            }

            public function getPath(): string
            {
                return $this->p;
            }
        }, ['Blog', 'ShopFront', 'Plain', 'Known']);
        $this->app->instance('modules', new readonly class($modules)
        {
            public function __construct(private array $m) {}

            public function allEnabled(): array
            {
                return $this->m;
            }
        });
        $this->app->instance(AssetManager::class, $this->assetManager);
        $this->assetManager->shouldReceive('getContainer')->andReturnUsing(
            fn (string $name) => $name === 'module.known' ? Mockery::mock(AssetContainer::class) : null
        );
        $calls = [];
        $this->moduleAssets->shouldReceive('setupModuleAssetContainer')->andReturnUsing(function (...$args) use (&$calls): string {
            $calls[] = $args;

            return 'x';
        });

        (new LaravelModuleAssets($this->app, $this->moduleAssets))->setUp();

        expect($calls)->toBe([
            ['blog', $this->dir.'/Blog', 'module', 'blog'],
            ['shop-front', $this->dir.'/ShopFront', 'module', 'shop-front'],
        ]);
    });
});

describe('LeanModuleMake activation', function (): void {
    it('is active only with nwidart loaded and no generator set by the project', function (): void {
        $template = new ModuleTemplate(new ModuleScaffolderService);
        $published = $this->dir.'/modules.php';

        $notLoaded = new LeanModuleMake(new Repository([]), $template, $published);
        $loaded = new LeanModuleMake(new Repository(['modules' => ['paths' => ['generator' => []]]]), $template, $published);
        $loadedActive = $loaded->isActive();
        file_put_contents($published, '<?php return ["paths" => []];');
        $overridden = new LeanModuleMake(new Repository(['modules' => ['paths' => ['generator' => []]]]), $template, $published);
        $overriddenActive = $overridden->isActive();
        file_put_contents($published, '<?php return ["stubs" => []];');
        $stubsActive = $overridden->isActive();
        file_put_contents($published, '<?php return ["connector" => "json"];');

        expect($notLoaded->isActive())->toBeFalse()
            ->and($loadedActive)->toBeTrue()
            ->and($overriddenActive)->toBeFalse()
            ->and($stubsActive)->toBeFalse()
            ->and($overridden->isActive())->toBeTrue();
    });

    it('writes nothing over a module when inactive or when the event carries something else', function (): void {
        $template = Mockery::mock(ModuleTemplate::class);
        $template->shouldReceive('writeDefault')->never();
        $inactive = new LeanModuleMake(new Repository([]), $template, $this->dir.'/none.php');
        $active = new LeanModuleMake(new Repository(['modules' => ['paths' => ['generator' => []]]]), $template, $this->dir.'/none.php');

        $inactive->writeOver(new class
        {
            public function getPath(): string
            {
                return '/x';
            }

            public function getName(): string
            {
                return 'X';
            }
        });
        $active->writeOver(new stdClass);

        expect($active->isActive())->toBeTrue();
    });
});
