<?php

declare(strict_types=1);

namespace Pollora\PostType\UI\Console;

use Pollora\Console\AbstractGeneratorCommand;
use Symfony\Component\Console\Input\InputArgument;

/**
 * Artisan command to generate a new custom post type class.
 *
 * This command creates a new PHP class that implements the PostType interface
 * and is configured with PHP attributes for WordPress custom post type registration.
 */
class PostTypeMakeCommand extends AbstractGeneratorCommand
{
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
     * The subpath where the class should be generated.
     *
     * @var string
     */
    protected string $subPath = 'Cms/PostTypes';

    /**
     * Get the stub file for the generator.
     */
    protected function getStub(): string
    {
        return __DIR__.'/stubs/posttype.stub';
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
