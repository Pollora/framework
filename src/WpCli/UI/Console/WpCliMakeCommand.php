<?php

declare(strict_types=1);

namespace Pollora\WpCli\UI\Console;

use Illuminate\Support\Str;
use Pollora\Console\AbstractGeneratorCommand;
use Symfony\Component\Console\Input\InputOption;

/**
 * Command to generate a new WP CLI command class.
 * This command creates a new WP CLI command class in the specified location (app, theme, or plugin).
 */
class WpCliMakeCommand extends AbstractGeneratorCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'pollora:make-wp-cli';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new WP CLI command class';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'WP CLI Command';

    /**
     * The subpath where the class should be generated.
     */
    protected string $subPath = 'Cms/Commands';

    /**
     * Get the stub file for the generator.
     */
    protected function getStub(): string
    {
        return file_get_contents(__DIR__ . '/stubs/wp-cli-simple.stub');
    }

    /**
     * Get the default namespace for the class.
     *
     * @param string $rootNamespace
     */
    protected function getDefaultNamespace($rootNamespace): string
    {
        $location = $this->resolveTargetLocation();

        return $this->getResolvedNamespace($location, 'Cms\\Commands');
    }

    /**
     * Build the class with the given name.
     *
     * @param string $name
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
     * Make replacements in the stub.
     */
    protected function makeReplacements(string $stub): string
    {
        $commandName = $this->getDefaultOption('command');
        $description = $this->getDefaultOption('description');
        
        return str_replace(
            ['{{ commandName }}', '{{ description }}'],
            [$commandName, $description],
            $stub
        );
    }

    /**
     * Get the console command options.
     */
    protected function getOptions(): array
    {
        return array_merge(
            parent::getOptions(),
            [
                ['command', 'c', InputOption::VALUE_OPTIONAL, 'The WP CLI command name (e.g., "my-command")'],
                ['description', 'd', InputOption::VALUE_OPTIONAL, 'The command description', 'A custom WP CLI command'],
                ['subcommands', 's', InputOption::VALUE_NONE, 'Generate a command class with subcommands support'],
                ['force', 'f', InputOption::VALUE_NONE, 'Create the class even if it already exists'],
            ]
        );
    }

    /**
     * Get the default option value.
     */
    protected function getDefaultOption(string $key): mixed
    {
        $defaults = [
            'command' => $this->generateCommandName(),
            'description' => 'A custom WP CLI command',
        ];

        return $this->option($key) ?? $defaults[$key];
    }

    /**
     * Generate a command name based on the class name.
     */
    protected function generateCommandName(): string
    {
        $className = $this->getNameInput();
        
        // Remove "Command" suffix if present
        if (Str::endsWith($className, 'Command')) {
            $className = substr($className, 0, -7);
        }
        
        // Convert to kebab-case
        return Str::kebab($className);
    }

    /**
     * Execute the console command.
     */
    public function handle(): ?bool
    {
        // Validate command name format
        $commandName = $this->getDefaultOption('command');
        if (!preg_match('/^[a-z0-9-]+$/', $commandName)) {
            $this->components->error('Command name must only contain lowercase letters, numbers, and hyphens.');
            return false;
        }

        return parent::handle();
    }
}