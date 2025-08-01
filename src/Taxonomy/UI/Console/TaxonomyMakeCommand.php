<?php

declare(strict_types=1);

namespace Pollora\Taxonomy\UI\Console;

use Pollora\Console\AbstractGeneratorCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

/**
 * Artisan command to generate a new custom taxonomy class.
 *
 * This command creates a new PHP class that implements the Taxonomy interface
 * and is configured with PHP attributes for WordPress custom taxonomy registration.
 */
class TaxonomyMakeCommand extends AbstractGeneratorCommand
{
    const OBJECT_TYPE_OPTION = 'object-type';

    const POST_TYPE_OPTION = 'post-type';

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'pollora:make-taxonomy';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new WordPress custom taxonomy class';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Taxonomy';

    /**
     * The subpath where the class should be generated.
     */
    protected string $subPath = 'Cms/Taxonomies';

    /**
     * Get the stub file for the generator.
     */
    protected function getStub(): string
    {
        return __DIR__.'/stubs/taxonomy.stub';
    }

    /**
     * Get the console command arguments.
     */
    protected function getArguments(): array
    {
        return [
            ['name', InputArgument::REQUIRED, 'The name of the taxonomy class'],
        ];
    }

    /**
     * Get the console command options.
     */
    protected function getOptions(): array
    {
        return array_merge(
            parent::getOptions(),
            [
                [static::POST_TYPE_OPTION, 'p', InputOption::VALUE_OPTIONAL, 'The post type to associate with this taxonomy (deprecated, use --object-type instead)', 'post'],
                [static::OBJECT_TYPE_OPTION, 'o', InputOption::VALUE_OPTIONAL, 'The post types to associate with this taxonomy (comma-separated)', null],
            ]
        );
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

        // Get object types
        $objectTypes = $this->getObjectTypes();

        // Replace placeholders in the stub
        $stub = str_replace('DummySlug', $this->getSlugFromClassName($className), $stub);
        $stub = str_replace('DummyName', $this->getNameFromClassName($className), $stub);
        $stub = str_replace('DummyPluralName', $this->getPluralNameFromClassName($className), $stub);

        return str_replace("['DummyPostType']", $objectTypes, $stub);
    }

    /**
     * Get the object types from the command options.
     */
    protected function getObjectTypes(): string
    {
        // Check if object-type option is provided
        if ($objectType = $this->option(static::OBJECT_TYPE_OPTION)) {
            // Split by comma and trim each value
            $types = array_map('trim', explode(',', $objectType));

            // Format as PHP array
            return $this->formatAsPhpArray($types);
        }

        // Fallback to post-type option
        return "['".$this->option(static::POST_TYPE_OPTION)."']";
    }

    /**
     * Format an array of strings as a PHP array representation.
     */
    protected function formatAsPhpArray(array $items): string
    {
        if (count($items) === 1) {
            return "['".$items[0]."']";
        }

        $formattedItems = array_map(fn (string $item): string => "'".$item."'", $items);

        return '['.implode(', ', $formattedItems).']';
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
