<?php

// Relocated from Commands/AttributeMakeCommand.php. Content will be copied verbatim and namespace updated.

declare(strict_types=1);

namespace Pollora\Hook\UI\Console;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Pollora\Attributes\Action;
use Pollora\Attributes\Filter;
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

    /**
     * Get the stub file for the generator.
     */
    protected function getStub(): string
    {
        return $this->resolveStubPath('/stubs/hook-attribute.stub');
    }

    /**
     * Resolve the fully-qualified path to the stub.
     */
    protected function resolveStubPath(string $stub): string
    {
        $customPath = config('stubs.path', base_path('stubs')).$stub;

        return file_exists($customPath) ? $customPath : __DIR__.$stub;
    }

    /**
     * Get the default namespace for the class.
     *
     * @param  string  $rootNamespace
     */
    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\\Cms\\Hooks';
    }

    /**
     * Build the class with the given name.
     *
     * @param  string  $name
     */
    protected function buildClass($name): string
    {
        $stub = parent::buildClass($name);

        return $this->makeReplacements($stub);
    }

    /**
     * Get the console command options.
     */
    protected function getOptions(): array
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
     * @throws FileNotFoundException
     */
    public function handle(): ?bool
    {
        $this->validateOptions();

        $path = $this->getSourceFilePath();
        $this->getNameInput();

        if ($this->alreadyExists($path)) {
            $this->updateExistingFile($path);
            $this->components->info(sprintf('%s [%s] updated successfully.', $this->type, $path));

            return null;
        }

        return parent::handle();
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
        $stub = $this->getUpdateStub();

        $hook = $this->getDefaultOption('hook');
        $hookMethodName = 'handle'.Str::studly(preg_replace('/[^a-zA-Z0-9]/', '', (string) $hook));

        $existingContent = File::get($path);

        // Ensure all required attribute imports are present
        $existingContent = $this->ensureAttributeImports($existingContent);

        $content = $this->makeReplacements(File::get($stub));

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
     *
     * This method checks for the presence of all necessary attribute imports
     * and adds them if they are missing. It maintains the existing imports
     * while adding any new ones that are required.
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
        return $this->resolveStubPath('/stubs/hook-attribute-update.stub');
    }

    /**
     * Get the source file path for the generated class.
     */
    protected function getSourceFilePath(): string
    {
        return app_path('Cms/Hooks/'.$this->getNameInput().'.php');
    }

    /**
     * Determine if the file already exists.
     *
     * @param  string  $rawName
     */
    protected function alreadyExists($rawName): bool
    {
        return File::exists($rawName);
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
