<?php

declare(strict_types=1);

namespace Pollora\Console;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Pollora\Foundation\Console\Commands\Concerns\HasModuleSupport;
use Pollora\Foundation\Console\Commands\Concerns\HasNameSupport;
use Pollora\Foundation\Console\Commands\Concerns\HasPathSupport;
use Pollora\Foundation\Console\Commands\Concerns\HasPluginSupport;
use Pollora\Foundation\Console\Commands\Concerns\HasThemeSupport;
use Pollora\Foundation\Console\Commands\Concerns\ResolvesLocation;

/**
 * Abstract class for generating files in different locations (app, theme, plugin, module).
 * Extends Laravel's GeneratorCommand to add support for custom locations.
 */
abstract class AbstractGeneratorCommand extends GeneratorCommand
{
    use HasModuleSupport, HasNameSupport, HasPathSupport, HasPluginSupport, HasThemeSupport, ResolvesLocation;

    /**
     * The subpath where the class should be generated.
     * This can be overridden by child classes to specify a custom subpath.
     */
    protected string $subPath = '';

    /**
     * Execute the console command.
     *
     * @throws FileNotFoundException
     */
    public function handle(): ?bool
    {
        // First we need to ensure that the given name is not a reserved word within the PHP
        // language and that the class name will actually be valid. If it is not valid we
        // can error now and prevent from polluting the filesystem using invalid files.
        if ($this->isReservedName($this->getNameInput())) {
            $this->components->error('The name "'.$this->getNameInput().'" is reserved by PHP.');

            return false;
        }

        // Get location info
        $location = $this->resolveTargetLocation();

        // Show where the file will be created
        $this->info("Creating {$this->type} in {$location['type']}: {$location['path']}");

        $name = $this->qualifyClass($this->getNameInput());
        $path = $this->getPath($name);

        // Next, We will check to see if the class already exists. If it does, we don't want
        // to create the class and overwrite the user's code. So, we will bail out so the
        // code is untouched. Otherwise, we will continue generating this class' files.
        if ((! $this->hasOption('force') ||
             ! $this->option('force')) &&
             $this->alreadyExists($this->getNameInput())) {
            $this->components->error($this->type.' already exists.');

            return false;
        }

        // Next, we will generate the path to the location where this class' file should get
        // written. Then, we will build the class and make the proper replacements on the
        // stub files so that it gets the correctly formatted namespace and class name.
        $this->makeDirectory($path);

        $this->files->put($path, $this->sortImports($this->buildClass($name)));

        $info = $this->type;

        if (windows_os()) {
            $path = str_replace('/', '\\', $path);
        }

        $this->components->success(sprintf('%s [%s] created successfully.', $info, $path));

        return null;
    }

    /**
     * Get the destination class path.
     *
     * @param  string  $name
     */
    protected function getPath($name): string
    {
        $location = $this->resolveTargetLocation();
        $className = str_replace($this->getNamespace($name).'\\', '', $name);

        return $this->getResolvedFilePath($location, $className, $this->subPath);
    }

    /**
     * Get the default namespace for the class.
     *
     * @param  string  $rootNamespace
     */
    protected function getDefaultNamespace($rootNamespace): string
    {
        $location = $this->resolveTargetLocation();

        return $this->getResolvedNamespace($location, $this->subPath);
    }

    /**
     * Get the root namespace for the class.
     */
    protected function rootNamespace(): string
    {
        $location = $this->resolveTargetLocation();

        return $location['namespace'];
    }

    /**
     * Get the console command options.
     */
    protected function getOptions(): array
    {
        return array_merge(
            $this->getPathOptions(),
            $this->getPluginOptions(),
            $this->getThemeOptions(),
            $this->getModuleOptions(),
            parent::getOptions()
        );
    }
}
