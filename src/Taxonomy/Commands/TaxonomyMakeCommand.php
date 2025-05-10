<?php

declare(strict_types=1);

namespace Pollora\Taxonomy\Commands;

use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

/**
 * Artisan command to generate a new custom taxonomy class.
 *
 * This command creates a new PHP class that implements the Taxonomy interface
 * and is configured with PHP attributes for WordPress custom taxonomy registration.
 */
class TaxonomyMakeCommand extends GeneratorCommand
{
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
     * Get the stub file for the generator.
     */
    protected function getStub(): string
    {
        return __DIR__.'/stubs/taxonomy.stub';
    }

    /**
     * Get the default namespace for the class.
     *
     * @param  string  $rootNamespace
     */
    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\Cms\Taxonomies';
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
        $directory = app_path('Cms/Taxonomies');
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
            ['name', InputArgument::REQUIRED, 'The name of the taxonomy class'],
        ];
    }

    /**
     * Get the console command options.
     */
    protected function getOptions(): array
    {
        return [
            ['post-type', 'p', InputOption::VALUE_OPTIONAL, 'The post type to associate with this taxonomy (deprecated, use --object-type instead)', 'post'],
            ['object-type', 'o', InputOption::VALUE_OPTIONAL, 'The post types to associate with this taxonomy (comma-separated)', null],
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
        if ($objectType = $this->option('object-type')) {
            // Split by comma and trim each value
            $types = array_map('trim', explode(',', $objectType));

            // Format as PHP array
            return $this->formatAsPhpArray($types);
        }

        // Fallback to post-type option
        return "['".$this->option('post-type')."']";
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
