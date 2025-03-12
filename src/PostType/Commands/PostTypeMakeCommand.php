<?php

declare(strict_types=1);

namespace Pollora\PostType\Commands;

use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Input\InputArgument;

/**
 * Artisan command to generate a new custom post type class.
 *
 * This command creates a new PHP class that implements the PostType interface
 * and is configured with PHP attributes for WordPress custom post type registration.
 */
class PostTypeMakeCommand extends GeneratorCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'make:posttype';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new WordPress custom post type class';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'PostType';

    /**
     * Get the stub file for the generator.
     */
    protected function getStub(): string
    {
        return __DIR__.'/stubs/posttype.stub';
    }

    /**
     * Get the default namespace for the class.
     *
     * @param  string  $rootNamespace
     */
    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\Cms\PostTypes';
    }

    /**
     * Execute the console command.
     *
     * @return bool|null
     *
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function handle()
    {
        // Create the directory if it doesn't exist
        $directory = app_path('Cms/PostTypes');
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
            $this->components->info(sprintf('Directory [%s] created successfully.', $directory));
        }

        return parent::handle();
    }

    /**
     * Get the console command arguments.
     */
    protected function getArguments(): array
    {
        return [
            ['name', InputArgument::REQUIRED, 'The name of the post type class'],
        ];
    }

    /**
     * Replace the class name for the given stub.
     *
     * @param  string  $stub
     * @param  string  $name
     */
    protected function replaceClass($stub, $name): string
    {
        $stub = parent::replaceClass($stub, $name);

        $className = class_basename($name);

        // Replace placeholders in the stub
        $stub = str_replace('DummySlug', $this->getSlugFromClassName($className), $stub);
        $stub = str_replace('DummyName', $this->getNameFromClassName($className), $stub);
        $stub = str_replace('DummyPluralName', $this->getPluralNameFromClassName($className), $stub);

        return $stub;
    }

    /**
     * Get the slug from the class name.
     */
    protected function getSlugFromClassName(string $className): string
    {
        return strtolower(preg_replace('/[A-Z]/', '-$0', lcfirst($className)));
    }

    /**
     * Get the singular name from the class name.
     */
    protected function getNameFromClassName(string $className): string
    {
        return ucfirst(strtolower(preg_replace('/[A-Z]/', ' $0', lcfirst($className))));
    }

    /**
     * Get the plural name from the class name.
     */
    protected function getPluralNameFromClassName(string $className): string
    {
        $name = $this->getNameFromClassName($className);

        // Simple pluralization (not comprehensive)
        if (substr($name, -1) === 'y') {
            return substr($name, 0, -1).'ies';
        }

        return $name.'s';
    }
}
