<?php

declare(strict_types=1);

namespace Pollora\Hook\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputOption;

/**
 * Class AttributeMakeCommand
 *
 * Abstract class for creating and updating hook attributes.
 */
abstract class AttributeMakeCommand extends GeneratorCommand
{
    /**
     * The type of the attribute.
     *
     * @var string
     */
    protected $type;

    use HookBootstrap;

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub()
    {
        $type = strtolower($this->type);

        return $this->resolveStubPath('/stubs/hook-attribute.stub');
    }

    /**
     * Resolve the fully-qualified path to the stub.
     *
     * @param  string  $stub
     * @return string
     */
    protected function resolveStubPath($stub)
    {
        $customPath = config('stubs.path', base_path('stubs')).$stub;

        return file_exists($customPath) ? $customPath : __DIR__.$stub;
    }

    /**
     * Get the default namespace for the class.
     *
     * @param  string  $rootNamespace
     * @return string
     */
    protected function getDefaultNamespace($rootNamespace)
    {
        return $rootNamespace.'\\Hooks';
    }

    /**
     * Build the class with the given name.
     *
     * @param  string  $name
     * @return string
     */
    protected function buildClass($name)
    {
        $stub = parent::buildClass($name);

        return $this->makeReplacements($stub);
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['force', 'f', InputOption::VALUE_NONE, 'Create the class even if the hook already exists'],
            ['hook', null, InputOption::VALUE_OPTIONAL, 'The WordPress hook to use', 'init'],
            ['priority', null, InputOption::VALUE_OPTIONAL, 'The hook priority', 10],
        ];
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->validateOptions();

        $path = $this->getSourceFilePath();
        $className = $this->getNameInput();

        if ($this->alreadyExists($path)) {
            $this->updateExistingFile($path);
            $this->components->info(sprintf('%s [%s] updated successfully.', $this->type, $path));

            return;
        }

        $result = parent::handle();

        if ($result === false) {
            return $result;
        }

        $this->addHookToBootstrap($this->qualifyClass($className));

        return $result;
    }

    /**
     * Validate the command options.
     *
     * @return void
     */
    protected function validateOptions()
    {
        $priority = $this->option('priority');
        if (! is_numeric($priority) || $priority < 0) {
            $this->error('The priority must be a non-negative number.');
            exit;
        }
    }

    /**
     * Update an existing file with new content.
     *
     * @param  string  $path
     * @return void
     */
    protected function updateExistingFile($path)
    {
        $stub = $this->getUpdateStub();

        $hook = $this->getDefaultOption('hook');
        $hookMethodName = 'handle'.Str::studly(preg_replace('/[^a-zA-Z0-9]/', '', $hook));

        $existingContent = File::get($path);

        // Check if the necessary attribute is already imported
        $type = $this->type;
        $attributeNamespace = "Pollora\\Attributes\\{$type}";
        $escapedNamespace = preg_quote($attributeNamespace, '/');
        if (! preg_match("/use {$escapedNamespace};/", $existingContent)) {
            // Find the last "use" statement and add the necessary attribute import
            $lastUsePosition = strrpos($existingContent, 'use ');
            $useStatements = substr($existingContent, 0, $lastUsePosition);
            $remainingContent = substr($existingContent, $lastUsePosition);
            $existingContent = $useStatements."use {$attributeNamespace};\n".$remainingContent;
        }

        $content = $this->makeReplacements(File::get($stub));

        if (preg_match("/public function {$hookMethodName}\(/", $existingContent)) {
            $this->error("The method '{$hookMethodName}' already exists in the file '{$path}'.");
            exit;
        }

        $lastClosingBracePosition = strrpos($existingContent, '}');

        $newContent = substr($existingContent, 0, $lastClosingBracePosition)."\n".$content."\n".substr($existingContent, $lastClosingBracePosition);

        File::put($path, $newContent);
    }

    /**
     * Make replacements in the stub.
     *
     * @return string
     */
    public function makeReplacements(string $stub)
    {
        $hook = $this->getDefaultOption('hook');
        $priority = $this->getDefaultOption('priority');
        $hookMethodName = 'handle'.Str::studly(preg_replace('/[^a-zA-Z0-9]/', '', $hook));
        $type = $this->type;

        $returnType = $type === 'Action' ? ': void' : '';
        $arg = $type === 'Filter' ? '$arg' : '';
        $return = $type === 'Filter' ? "\n".'        return $arg;' : '';

        return str_replace(
            ['{{ type }}', '{{ hook }}', '{{ priority }}', '{{ hookMethodName }}',
                '{{ arg }}', '{{ returnType }}', '{{ return }}'],
            [$type, $hook, $priority, $hookMethodName, $arg, $returnType, $return],
            $stub
        );
    }

    /**
     * Get the update stub file path.
     *
     * @return string
     */
    protected function getUpdateStub()
    {
        return $this->resolveStubPath('/stubs/hook-attribute-update.stub');
    }

    /**
     * Get the source file path for the generated class.
     *
     * @return string
     */
    protected function getSourceFilePath()
    {
        return app_path('Hooks/'.$this->getNameInput().'.php');
    }

    /**
     * Determine if the file already exists.
     *
     * @param  string  $path
     * @return bool
     */
    protected function alreadyExists($path)
    {
        return File::exists($path);
    }

    /**
     * Get the default option value.
     *
     * @param  string  $key
     * @return mixed
     */
    protected function getDefaultOption($key)
    {
        $defaults = [
            'hook' => 'init',
            'priority' => 10,
        ];

        return $this->option($key) ?? $defaults[$key];
    }
}
