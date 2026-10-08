<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Pollora\Asset\Application\Services\AssetManager;
use Pollora\Asset\Infrastructure\Providers\AssetServiceProvider;
use Pollora\Modules\Infrastructure\Services\LaravelModuleAssets;
use Pollora\Modules\Infrastructure\Services\LeanModuleMake;
use Pollora\Modules\Infrastructure\Services\ModuleTemplate;
use Pollora\Modules\UI\Console\MakeModuleCommand;

beforeEach(function (): void {
    $this->modulesPath = sys_get_temp_dir().'/pollora-make-module-'.uniqid();
    config(['modules.paths.modules' => $this->modulesPath, 'modules.namespace' => 'Modules']);
    Process::fake();
    $this->app->make(Kernel::class)->registerCommand($this->app->make(MakeModuleCommand::class));
});

afterEach(function (): void {
    File::deleteDirectory($this->modulesPath);
});

/**
 * Every file of a generated module, relative to it.
 *
 * @return list<string>
 */
function moduleFiles(string $modulePath): array
{
    $files = array_map(
        fn (SplFileInfo $file): string => str_replace($modulePath.'/', '', $file->getPathname()),
        File::allFiles($modulePath, true),
    );
    sort($files);

    return $files;
}

describe('pollora:make:module', function (): void {
    it('writes the lean module: discovered classes, blocks, a Vite build, no provider', function (): void {
        $this->artisan('pollora:make:module', ['name' => 'Crm', '--offline' => true, '--no-npm' => true, '--no-enable' => true])
            ->assertSuccessful();

        $modulePath = $this->modulesPath.'/Crm';

        expect(moduleFiles($modulePath))->toBe([
            'README.md',
            'app/Cms/Hooks/CrmHooks.php',
            'composer.json',
            'module.json',
            'package.json',
            'resources/assets/app.css',
            'resources/assets/app.js',
            'vite.config.js',
        ])
            ->and($modulePath.'/resources/views/blocks')->toBeDirectory()
            ->and(json_decode((string) file_get_contents($modulePath.'/module.json'), true))->toMatchArray(['name' => 'Crm', 'alias' => 'crm', 'providers' => []])
            ->and(json_decode((string) file_get_contents($modulePath.'/composer.json'), true)['autoload']['psr-4'])->toBe(['Modules\\Crm\\' => 'app/'])
            ->and((string) file_get_contents($modulePath.'/vite.config.js'))->toContain("pollora({ type: 'module', name: 'crm' })")
            ->and((string) file_get_contents($modulePath.'/app/Cms/Hooks/CrmHooks.php'))->toContain('namespace Modules\\Crm\\Cms\\Hooks;')
            ->toContain("->container('module.crm')");

        foreach (moduleFiles($modulePath) as $file) {
            expect((string) file_get_contents($modulePath.'/'.$file))->not->toMatch('/%[a-z_]+%/');
        }

        Process::assertRan(fn ($process): bool => $process->command === ['composer', 'dump-autoload', '--no-interaction', '--no-scripts']);
    });

    it('kebab-cases the slug of a multi-word module', function (): void {
        $this->artisan('pollora:make:module', ['name' => 'BlocksDemo', '--offline' => true, '--no-npm' => true, '--no-enable' => true])
            ->assertSuccessful();

        expect((string) file_get_contents($this->modulesPath.'/BlocksDemo/vite.config.js'))->toContain("name: 'blocks-demo'");
    });

    it('leaves the Vite build out with --no-assets', function (): void {
        $this->artisan('pollora:make:module', ['name' => 'Crm', '--offline' => true, '--no-assets' => true, '--no-enable' => true])
            ->assertSuccessful();

        expect(moduleFiles($this->modulesPath.'/Crm'))->toBe(['README.md', 'app/Cms/Hooks/CrmHooks.php', 'composer.json', 'module.json']);
    });

    it('adds every Laravel layer with --full', function (): void {
        $this->artisan('pollora:make:module', ['name' => 'Crm', '--offline' => true, '--full' => true, '--no-npm' => true, '--no-enable' => true])
            ->assertSuccessful();

        $modulePath = $this->modulesPath.'/Crm';
        $composer = json_decode((string) file_get_contents($modulePath.'/composer.json'), true);
        $provider = (string) file_get_contents($modulePath.'/app/Providers/CrmServiceProvider.php');

        expect($modulePath.'/app/Providers/RouteServiceProvider.php')->toBeFile()
            ->and($modulePath.'/routes/web.php')->toBeFile()
            ->and($modulePath.'/routes/api.php')->toBeFile()
            ->and($modulePath.'/config/config.php')->toBeFile()
            ->and($modulePath.'/database/migrations')->toBeDirectory()
            ->and($modulePath.'/tests/Feature/CrmTest.php')->toBeFile()
            ->and($provider)->toContain('protected array $providers = [\\Modules\\Crm\\Providers\\RouteServiceProvider::class];')
            ->toContain("protected string \$nameLower = 'crm';")
            ->and(json_decode((string) file_get_contents($modulePath.'/module.json'), true)['providers'])->toBe(['Modules\\Crm\\Providers\\CrmServiceProvider'])
            ->and($composer['autoload']['psr-4'])->toHaveKey('Modules\\Crm\\Database\\Seeders\\')
            ->and($composer['autoload-dev']['psr-4'])->toBe(['Modules\\Crm\\Tests\\' => 'tests/']);

        foreach (['app/Providers/CrmServiceProvider.php', 'app/Providers/RouteServiceProvider.php', 'routes/web.php', 'routes/api.php', 'config/config.php', 'tests/Feature/CrmTest.php'] as $file) {
            exec('php -l '.escapeshellarg($modulePath.'/'.$file), $output, $exitCode);
            expect($exitCode)->toBe(0, $file);
        }
    });

    it('adds the provider --routes needs, and no more', function (): void {
        $this->artisan('pollora:make:module', ['name' => 'Crm', '--offline' => true, '--routes' => true, '--no-npm' => true, '--no-enable' => true])
            ->assertSuccessful();

        $modulePath = $this->modulesPath.'/Crm';

        expect($modulePath.'/app/Providers/CrmServiceProvider.php')->toBeFile()
            ->and($modulePath.'/routes/web.php')->toBeFile()
            ->and($modulePath.'/routes/api.php')->not->toBeFile()
            ->and($modulePath.'/config')->not->toBeDirectory();
    });

    it('refuses an existing module unless forced', function (): void {
        File::ensureDirectoryExists($this->modulesPath.'/Crm');

        $this->artisan('pollora:make:module', ['name' => 'Crm', '--offline' => true, '--no-enable' => true])
            ->expectsOutputToContain('already exists')
            ->assertFailed();
    });

    it('refuses a name that is not a class name', function (): void {
        $this->artisan('pollora:make:module', ['name' => 'my-crm', '--offline' => true])->assertFailed();
    });
});

describe('module:make', function (): void {
    it('turns off the stock module and writes the lean one over it', function (): void {
        config(['modules.paths.generator' => ['controller' => ['path' => 'app/Http/Controllers', 'generate' => true]], 'modules.stubs.files' => ['routes/web' => 'routes/web.php']]);
        $lean = new LeanModuleMake(config(), $this->app->make(ModuleTemplate::class), $this->modulesPath.'/no-published-config.php');

        $lean->applyDefaults();
        $lean->writeOver(new readonly class($this->modulesPath.'/Crm')
        {
            public function __construct(private string $path) {}

            public function getPath(): string
            {
                return $this->path;
            }

            public function getName(): string
            {
                return 'Crm';
            }
        });

        expect(config('modules.paths.generator.controller.generate'))->toBeFalse()
            ->and(config('modules.stubs.files'))->toBe([])
            ->and($this->modulesPath.'/Crm/app/Cms/Hooks/CrmHooks.php')->toBeFile()
            ->and($this->modulesPath.'/Crm/vite.config.js')->toBeFile();
    });

    it('keeps the stock module of a project that published config/modules.php', function (): void {
        File::ensureDirectoryExists($this->modulesPath);
        file_put_contents($this->modulesPath.'/modules.php', '<?php return [];');
        config(['modules.paths.generator' => ['controller' => ['path' => 'app/Http/Controllers', 'generate' => true]]]);

        (new LeanModuleMake(config(), $this->app->make(ModuleTemplate::class), $this->modulesPath.'/modules.php'))->applyDefaults();

        expect(config('modules.paths.generator.controller.generate'))->toBeTrue();
    });
});

describe('LaravelModuleAssets', function (): void {
    it('gives an enabled module with a Vite build its asset container', function (): void {
        $this->app->register(AssetServiceProvider::class);
        $this->artisan('pollora:make:module', ['name' => 'Crm', '--offline' => true, '--no-npm' => true, '--no-enable' => true]);
        $this->artisan('pollora:make:module', ['name' => 'Plain', '--offline' => true, '--no-assets' => true, '--no-enable' => true]);

        $modules = array_map(fn (string $name): object => new readonly class($this->modulesPath.'/'.$name, $name)
        {
            public function __construct(private string $path, private string $name) {}

            public function getPath(): string
            {
                return $this->path;
            }

            public function getName(): string
            {
                return $this->name;
            }
        }, ['Crm', 'Plain']);

        $this->app->instance('modules', new readonly class($modules)
        {
            public function __construct(private array $modules) {}

            public function allEnabled(): array
            {
                return $this->modules;
            }
        });

        $this->app->make(LaravelModuleAssets::class)->setUp();
        $assets = $this->app->make(AssetManager::class);

        expect($assets->getContainer('module.crm'))->not->toBeNull()
            ->and($assets->getContainer('module.plain'))->toBeNull();
    });
});
