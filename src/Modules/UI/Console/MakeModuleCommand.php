<?php

declare(strict_types=1);

namespace Pollora\Modules\UI\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Pollora\Modules\Infrastructure\Services\ModuleScaffolderService;
use Pollora\Modules\Infrastructure\Services\ModuleTemplate;
use Pollora\Support\NpmRunner;

/**
 * Create a lean Laravel module from the Pollora/module-default template.
 *
 * The module holds what a Pollora module uses — classes discovered in app/,
 * Blade blocks, a Vite build shared with themes and plugins — and each Laravel
 * layer (provider, routes, config, database, tests) is one flag away.
 */
#[Description('Create a module from the Pollora/module-default template')]
#[Signature('pollora:make:module {name : Module name, in StudlyCase (Crm, BlocksDemo)}
    {--description= : Module description}
    {--author= : Module author}
    {--repository=Pollora/module-default : GitHub repository of the template (owner/repo)}
    {--repo-version= : Tag of the template to download}
    {--provider : Add a service provider, listed in module.json}
    {--routes : Add routes/web.php and the provider that loads it (implies --provider)}
    {--api : Add routes/api.php (implies --routes)}
    {--config : Add config/config.php, merged as <name>.* (implies --provider)}
    {--database : Add database/migrations, seeders and factories}
    {--tests : Add tests/Feature, tests/Unit and a Pest test}
    {--full : Every layer above}
    {--no-assets : Leave out package.json, vite.config.js and resources/assets}
    {--no-npm : Do not run npm install and npm run build}
    {--no-enable : Leave the module disabled}
    {--offline : Use the template bundled with the framework instead of downloading it}
    {--force : Overwrite an existing module directory}')]
class MakeModuleCommand extends Command
{
    public function __construct(
        protected Filesystem $files,
        protected ModuleScaffolderService $scaffolder,
        protected ModuleTemplate $template,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $name = (string) $this->argument('name');

        if (preg_match('/^[A-Za-z][A-Za-z0-9]*$/', $name) !== 1) {
            $this->components->error(sprintf('Invalid module name "%s": letters and digits only, starting with a letter (e.g. Crm).', $name));

            return self::FAILURE;
        }

        $name = Str::studly($name);
        $modulesPath = rtrim((string) config('modules.paths.modules', base_path('Modules')), '/');
        $modulePath = $modulesPath.'/'.$name;

        if ($this->files->isDirectory($modulePath)) {
            if (! $this->option('force')) {
                $this->components->error(sprintf('Module "%s" already exists in %s. Use --force to overwrite it.', $name, $modulePath));

                return self::FAILURE;
            }

            $this->files->deleteDirectory($modulePath);
        }

        $replacements = $this->template->replacements(
            $name,
            (string) config('modules.namespace', 'Modules'),
            (string) $this->option('description'),
            (string) $this->option('author'),
        );
        $withAssets = ! $this->option('no-assets');

        $this->scaffold($modulesPath, $modulePath, $replacements, $withAssets);

        $layers = $this->template->resolveLayers($this->requestedLayers());

        if ($layers !== []) {
            $this->template->addLayers($modulePath, $layers, $replacements);
        }

        $this->components->info(sprintf('Module [%s] created in %s%s.', $name, $modulePath, $layers === [] ? '' : ' with '.implode(', ', $layers)));

        $this->dumpAutoload();

        if (! $this->option('no-enable')) {
            $this->enable($name);
        }

        if ($withAssets && ! $this->option('no-npm')) {
            $this->buildAssets($modulePath);
        }

        return self::SUCCESS;
    }

    /**
     * Download the template, or write the bundled copy when asked to or when
     * the download fails.
     *
     * @param  array<string, string>  $replacements
     */
    protected function scaffold(string $modulesPath, string $modulePath, array $replacements, bool $withAssets): void
    {
        if (! $this->option('offline')) {
            $this->scaffolder->ensureDirectoryExists($modulesPath);

            $downloaded = $this->scaffolder->downloadAndScaffold(
                repository: (string) $this->option('repository'),
                basePath: $modulesPath,
                targetPath: $modulePath,
                replacements: $replacements,
                version: $this->option('repo-version'),
                output: $this->getOutput(),
                fileFilter: fn (object $item): bool => $item->getRelativePathname() !== 'LICENSE'
                    && ($withAssets || ! $this->template->isAssetFile($item->getRelativePathname())),
                removeDirs: ['bin', '.github'],
            );

            if ($downloaded) {
                // Hidden files are not copied: the empty blocks directory is kept by a .gitkeep upstream
                $this->scaffolder->ensureDirectoryExists($modulePath.'/resources/views/blocks');

                return;
            }

            $this->components->warn('Using the template bundled with the framework.');
        }

        $this->template->writeDefault($modulePath, $replacements, $withAssets);
    }

    /**
     * Layers named by the options.
     *
     * @return list<string>
     */
    protected function requestedLayers(): array
    {
        if ($this->option('full')) {
            return array_keys(ModuleTemplate::LAYERS);
        }

        return array_values(array_filter(
            array_keys(ModuleTemplate::LAYERS),
            fn (string $layer): bool => (bool) $this->option($layer),
        ));
    }

    /**
     * Merge the module's composer.json into the autoloader, through
     * wikimedia/composer-merge-plugin.
     */
    protected function dumpAutoload(): void
    {
        $result = Process::path(base_path())->timeout(300)->run(['composer', 'dump-autoload', '--no-interaction', '--no-scripts']);

        if (! $result->successful()) {
            $this->components->warn('composer dump-autoload failed: run it before using the module, so its classes autoload.');
        }
    }

    /**
     * Enable the module through nwidart/laravel-modules, which writes the state
     * where its activator keeps it.
     */
    protected function enable(string $name): void
    {
        if (! $this->getApplication()?->has('module:enable')) {
            $this->components->warn('nwidart/laravel-modules is not installed: the module was not enabled.');

            return;
        }

        if ($this->laravel->bound('modules')) {
            $this->laravel->make('modules')->resetModules();
        }

        $this->callSilently('module:enable', ['module' => [$name]]);
        $this->components->info(sprintf('Module [%s] enabled.', $name));
    }

    protected function buildAssets(string $modulePath): void
    {
        $this->components->info('Running npm install and npm run build in '.$modulePath.'...');

        try {
            (new NpmRunner($modulePath))->install()->build();
        } catch (\Throwable $throwable) {
            $this->components->warn('npm install or build failed: '.$throwable->getMessage());
        }
    }
}
