<?php

declare(strict_types=1);

namespace Pollora\PostType\UI\Console;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Pollora\Console\Domain\Shared\Traits\HasModulePathSupport;
use Pollora\Console\Domain\Shared\Traits\HasNameSupport;
use Pollora\Console\Domain\Shared\Traits\HasPathSupport;
use Pollora\Console\Domain\Shared\Traits\HasPluginPathSupport;
use Symfony\Component\Console\Input\InputArgument;

/**
 * Artisan command to generate a new custom post type class.
 *
 * This command creates a new PHP class that implements the PostType interface
 * and is configured with PHP attributes for WordPress custom post type registration.
 */
class PostTypeMakeCommand extends GeneratorCommand
{
    use HasNameSupport;
    use HasModulePathSupport;
    use HasPluginPathSupport;
    use HasPathSupport;
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'pollora:make-posttype';

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
     *
     * @throws FileNotFoundException
     */
    public function handle(): ?bool
    {
        // Create the directory if it doesn't exist
        $directory = app_path('Cms/PostTypes');
        if (! is_dir($directory)) {
            if (! mkdir($directory, 0755, true) && ! is_dir($directory)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $directory));
            }
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
            static::getNameArgDefinition('The name of the post type class'),
            //static::getPluginPathArgDefinition(),
            //static::getModulePathArgDefinition(),
            //static::getPathArgDefinition(),
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
        return str_replace(
            ['DummySlug', 'DummyName', 'DummyPluralName'],
            [$this->getSlugFromClassName($className), $this->getNameFromClassName($className), $this->getPluralNameFromClassName($className)],
            $stub);
    }

    /**
     * Get the slug from the class name.
     */
    protected function getSlugFromClassName(string $className): string
    {
        return strtolower((string) preg_replace('/[A-Z]/', '-$0', lcfirst($className)));
    }

    /**
     * Get the singular name from the class name.
     */
    protected function getNameFromClassName(string $className): string
    {
        return ucfirst(strtolower((string) preg_replace('/[A-Z]/', ' $0', lcfirst($className))));
    }

    /**
     * Get the plural name from the class name.
     */
    protected function getPluralNameFromClassName(string $className): string
    {
        $name = $this->getNameFromClassName($className);

        // Simple pluralization (not comprehensive)
        if (str_ends_with($name, 'y')) {
            return substr($name, 0, -1).'ies';
        }

        return $name.'s';
    }
}
