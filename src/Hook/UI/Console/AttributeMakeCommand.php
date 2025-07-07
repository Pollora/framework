<?php

// Relocated from Commands/AttributeMakeCommand.php. Content will be copied verbatim and namespace updated.

declare(strict_types=1);

namespace Pollora\Hook\UI\Console;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Pollora\Attributes\Action;
use Pollora\Attributes\Filter;
use Pollora\Console\AbstractGeneratorCommand;
use Symfony\Component\Console\Input\InputOption;

/**
 * Command to generate a new hook attribute class.
 * This command creates a new hook attribute class in the specified location (app, theme, or plugin).
 */
class AttributeMakeCommand extends AbstractGeneratorCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'pollora:make-hook';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new hook attribute class';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Hook';

    /**
     * The subpath where the class should be generated.
     */
    protected string $subPath = 'Cms/Hooks';

    /**
     * Get the stub file for the generator.
     */
    protected function getStub(): string
    {
        return file_get_contents(__DIR__.'/stubs/hook-attribute.stub');
    }

    /**
     * Get the default namespace for the class.
     *
     * @param  string  $rootNamespace
     */
    protected function getDefaultNamespace($rootNamespace): string
    {
        $location = $this->resolveTargetLocation();

        return $this->getResolvedNamespace($location, 'Cms\\Hooks');
    }

    /**
     * Build the class with the given name.
     *
     * @param  string  $name
     */
    protected function buildClass($name): string
    {
        $stub = $this->getStub();

        // Get the namespace
        $namespace = $this->getDefaultNamespace($this->rootNamespace());

        // Replace namespace in stub
        $stub = str_replace(
            ['{{ namespace }}', '{{namespace}}'],
            $namespace,
            $stub
        );

        // Replace class name in stub
        $stub = str_replace(
            ['{{ class }}', '{{class}}'],
            class_basename($name),
            $stub
        );

        // Make other replacements
        return $this->makeReplacements($stub);
    }

    /**
     * Execute the console command.
     *
     * @throws FileNotFoundException
     */
    public function handle(): ?bool
    {
        $this->validateOptions();

        $path = $this->getSourceFilePath();
        $this->getNameInput();
        if (File::exists($path)) {
            $this->updateExistingFile($path);
            $this->components->info(sprintf('%s [%s] updated successfully.', $this->type, $path));

            return null;
        }

        return parent::handle();
    }

    /**
     * Get the source file path for the generated class.
     */
    protected function getSourceFilePath(): string
    {
        $location = $this->resolveTargetLocation();
        $className = $this->getNameInput();

        return $this->getResolvedFilePath($location, $className, 'Cms/Hooks');
    }

    /**
     * Validate the command options.
     */
    protected function validateOptions(): void
    {
        $priority = $this->option('priority');
        if (! is_numeric($priority) || $priority < 0) {
            $this->error('The priority must be a non-negative number.');
            exit;
        }
    }

    /**
     * Update an existing file with new content.
     */
    protected function updateExistingFile(string $path): void
    {
        $stubPath = $this->getUpdateStub();
        $stub = File::get($stubPath);

        $hook = $this->getDefaultOption('hook');
        $hookMethodName = 'handle'.Str::studly(preg_replace('/[^a-zA-Z0-9]/', '', (string) $hook));

        $existingContent = File::get($path);

        // Ensure all required attribute imports are present
        $existingContent = $this->ensureAttributeImports($existingContent);

        $content = $this->makeReplacements($stub);

        if (preg_match("/public function {$hookMethodName}\(/", $existingContent)) {
            $this->error("The method '{$hookMethodName}' already exists in the file '{$path}'.");
            exit;
        }

        $lastClosingBracePosition = strrpos($existingContent, '}');

        $newContent = substr($existingContent, 0, $lastClosingBracePosition)."\n".$content.substr($existingContent, $lastClosingBracePosition);

        File::put($path, $newContent);
    }

    /**
     * Ensure all required attribute imports are present in the file content.
     */
    protected function ensureAttributeImports(string $content): string
    {
        $requiredImports = [
            Action::class,
            Filter::class,
        ];

        foreach ($requiredImports as $import) {
            $escapedImport = preg_quote($import, '/');
            if (in_array(preg_match("/use {$escapedImport};/", $content), [0, false], true)) {
                // Find the last "use" statement
                $lastUsePosition = strrpos($content, 'use ');
                if ($lastUsePosition !== false) {
                    $useStatements = substr($content, 0, $lastUsePosition);
                    $remainingContent = substr($content, $lastUsePosition);
                    $content = $useStatements."use {$import};\n".$remainingContent;
                }
            }
        }

        return $content;
    }

    /**
     * Make replacements in the stub.
     */
    public function makeReplacements(string $stub): string
    {
        $hook = $this->getDefaultOption('hook');
        $priority = $this->getDefaultOption('priority');
        $hookMethodName = 'handle'.Str::studly(preg_replace('/[^a-zA-Z0-9]/', '', (string) $hook));
        $hookType = $this->type;

        $returnType = $hookType === 'Action' ? ': void' : '';
        $arg = $hookType === 'Filter' ? '$arg' : '';
        $return = $hookType === 'Filter' ? "\n".'        return $arg;' : '';

        return str_replace(
            ['{{ hookType }}', '{{ hook }}', '{{ priority }}', '{{ hookMethodName }}',
                '{{ arg }}', '{{ returnType }}', '{{ return }}'],
            [$hookType, $hook, ", priority:{$priority}", $hookMethodName, $arg, $returnType, $return],
            $stub
        );
    }

    /**
     * Get the update stub file path.
     */
    protected function getUpdateStub(): string
    {
        return __DIR__.'/stubs/hook-attribute-update.stub';
    }

    /**
     * Get the console command options.
     */
    protected function getOptions(): array
    {
        return array_merge(
            parent::getOptions(),
            [
                ['force', 'f', InputOption::VALUE_NONE, 'Create the class even if the hook already exists'],
                ['hook', null, InputOption::VALUE_OPTIONAL, 'The hook to use'],
                ['priority', null, InputOption::VALUE_OPTIONAL, 'The hook priority', 10],
            ]
        );
    }

    /**
     * Get the default option value.
     */
    protected function getDefaultOption(string $key): mixed
    {
        $defaults = [
            'hook' => 'init',
            'priority' => 10,
        ];

        return $this->option($key) ?? $defaults[$key];
    }
}
