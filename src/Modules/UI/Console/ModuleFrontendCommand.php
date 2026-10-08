<?php

declare(strict_types=1);

namespace Pollora\Modules\UI\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Pollora\Modules\Infrastructure\Services\ModuleTemplate;

/**
 * Give an existing module the frontend stack of the module template:
 * package.json and vite.config.js on @pollora/vite-config, and
 * resources/assets/app.{js,css}. Each file it replaces is kept as <file>.bak.
 *
 * A module made by nwidart's stock module:make builds into public/build-<lower>,
 * where Pollora never looks; this is the way out. Nothing runs at upgrade: a
 * project's modules are its own code.
 */
#[Description('Give a module the frontend build of the Pollora module template')]
#[Signature('pollora:module:frontend {module : Module name}
    {--no-backup : Replace the files without keeping a .bak copy}')]
class ModuleFrontendCommand extends Command
{
    /**
     * Template files written into the module.
     *
     * @var list<string>
     */
    private const array FILES = ['package.json', 'vite.config.js', 'resources/assets/app.js', 'resources/assets/app.css'];

    public function handle(ModuleTemplate $template, Filesystem $files): int
    {
        $module = $this->laravel->bound('modules') ? $this->laravel->make('modules')->find((string) $this->argument('module')) : null;

        if ($module === null) {
            $this->components->error(sprintf('Module "%s" does not exist.', $this->argument('module')));

            return self::FAILURE;
        }

        $modulePath = rtrim((string) $module->getPath(), '/');
        $replacements = $template->replacements((string) $module->getName(), (string) config('modules.namespace', 'Modules'));

        foreach (self::FILES as $file) {
            $target = $modulePath.'/'.$file;
            $existed = $files->exists($target);

            if ($existed && ! $this->option('no-backup')) {
                $files->copy($target, $target.'.bak');
            }

            $files->ensureDirectoryExists(dirname($target));
            $files->put($target, strtr($files->get($template->defaultTemplatePath().'/'.$file), $replacements));

            $this->components->twoColumnDetail($file, $existed ? ($this->option('no-backup') ? 'REPLACED' : 'REPLACED (backup: '.basename($file).'.bak)') : 'CREATED');
        }

        $this->newLine();
        $this->line(sprintf('  Next: <comment>cd %s && npm install && npm run build</comment>.', $this->relative($modulePath)));
        $this->line('  Entries other than resources/assets/app.js go in the <comment>input</comment> option of pollora() in vite.config.js.');

        return self::SUCCESS;
    }

    private function relative(string $path): string
    {
        return ltrim(str_replace(base_path(), '', $path), '/');
    }
}
