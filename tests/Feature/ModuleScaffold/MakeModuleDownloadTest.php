<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Pollora\Modules\UI\Console\MakeModuleCommand;

beforeEach(function (): void {
    $this->root = sys_get_temp_dir().'/pollora-make-module-dl-'.uniqid();
    $this->modulesPath = $this->root.'/Modules';
    File::ensureDirectoryExists($this->root.'/storage');
    $this->app->useStoragePath($this->root.'/storage');
    config(['modules.paths.modules' => $this->modulesPath, 'modules.namespace' => 'Modules']);
    Process::fake();
    $this->app->make(Kernel::class)->registerCommand($this->app->make(MakeModuleCommand::class));
});

afterEach(function (): void {
    File::deleteDirectory($this->root);
});

function templateArchive(string $path): string
{
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE);

    $files = [
        'module-default-main/README.md' => '# %module_name%',
        'module-default-main/LICENSE' => 'MIT',
        'module-default-main/module.json' => '{"name": "%module_name%", "providers": []}',
        'module-default-main/composer.json' => '{"name": "x/%module_alias%"}',
        'module-default-main/package.json' => '{"name": "%module_alias%"}',
        'module-default-main/vite.config.js' => '// vite %module_alias%',
        'module-default-main/resources/assets/app.js' => '// app',
        'module-default-main/bin/release.sh' => 'x',
        'module-default-main/.github/workflows/ci.yml' => 'x',
    ];
    foreach ($files as $name => $content) {
        $zip->addFromString($name, $content);
    }

    $zip->close();

    return (string) file_get_contents($path);
}

/**
 * @return list<string>
 */
function generatedFiles(string $modulePath): array
{
    $files = array_map(
        fn (SplFileInfo $file): string => str_replace($modulePath.'/', '', $file->getPathname()),
        File::allFiles($modulePath, true),
    );
    sort($files);

    return $files;
}

describe('pollora:make:module from the template repository', function (): void {
    it('downloads the template, leaves out LICENSE, bin and .github, and keeps the blocks directory', function (): void {
        Http::fake(['github.com/*' => Http::response(templateArchive($this->root.'/t.zip'))]);

        $status = Artisan::call('pollora:make:module', ['name' => 'Crm', '--repo-version' => 'v1.0.0', '--no-npm' => true, '--no-enable' => true]);
        $module = $this->modulesPath.'/Crm';

        expect($status)->toBe(0)
            ->and(generatedFiles($module))->toBe(['README.md', 'composer.json', 'module.json', 'package.json', 'resources/assets/app.js', 'vite.config.js'])
            ->and($module.'/resources/views/blocks')->toBeDirectory()
            ->and(file_get_contents($module.'/README.md'))->toBe('# Crm')
            ->and(is_dir($this->modulesPath.'/module-default'))->toBeFalse();
        Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/Pollora/module-default/archive/refs/tags/v1.0.0.zip'));
    });

    it('leaves the Vite build files of the template out with --no-assets', function (): void {
        Http::fake(['github.com/*' => Http::response(templateArchive($this->root.'/t.zip'))]);

        Artisan::call('pollora:make:module', ['name' => 'Crm', '--repo-version' => 'v1', '--no-assets' => true, '--no-enable' => true]);

        expect(generatedFiles($this->modulesPath.'/Crm'))->toBe(['README.md', 'composer.json', 'module.json']);
    });

    it('writes the bundled template when the download fails', function (): void {
        Http::fake(['*' => Http::response('', 404)]);

        $status = Artisan::call('pollora:make:module', ['name' => 'Crm', '--repo-version' => 'v9', '--no-npm' => true, '--no-enable' => true]);

        expect($status)->toBe(0)
            ->and(Artisan::output())->toContain('Using the template bundled with the framework')
            ->and($this->modulesPath.'/Crm/module.json')->toBeFile()
            ->and($this->modulesPath.'/Crm/app/Cms/Hooks/CrmHooks.php')->toBeFile();
    });
});

describe('pollora:make:module overwriting and enabling', function (): void {
    it('replaces an existing module with --force', function (): void {
        File::ensureDirectoryExists($this->modulesPath.'/Crm');
        file_put_contents($this->modulesPath.'/Crm/old.txt', 'old');

        $status = Artisan::call('pollora:make:module', ['name' => 'Crm', '--offline' => true, '--force' => true, '--no-npm' => true, '--no-enable' => true]);

        expect($status)->toBe(0)
            ->and($this->modulesPath.'/Crm/old.txt')->not->toBeFile()
            ->and($this->modulesPath.'/Crm/module.json')->toBeFile();
    });

    it('warns instead of enabling when nwidart is not installed', function (): void {
        $status = Artisan::call('pollora:make:module', ['name' => 'Crm', '--offline' => true, '--no-npm' => true]);

        expect($status)->toBe(0)
            ->and(Artisan::output())->toContain('nwidart/laravel-modules is not installed')
            ->and($this->modulesPath.'/Crm')->toBeDirectory();
    });

    it('enables the module through module:enable when it is available', function (): void {
        $command = new #[\Illuminate\Console\Attributes\Signature('module:enable {module*}')] class extends Command
        {
            /** @var list<string> */
            public static array $enabled = [];

            public function handle(): void
            {
                self::$enabled = $this->argument('module');
            }
        };
        $command::$enabled = [];
        $this->app->make(Kernel::class)->registerCommand($command);

        Artisan::call('pollora:make:module', ['name' => 'BlocksDemo', '--offline' => true, '--no-npm' => true]);

        expect($command::$enabled)->toBe(['BlocksDemo'])
            ->and(Artisan::output())->toContain('Module [BlocksDemo] enabled');
    });
});
