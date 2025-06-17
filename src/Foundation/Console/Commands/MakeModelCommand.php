<?php

declare(strict_types=1);

namespace Pollora\Foundation\Console\Commands;

use Pollora\Console\AbstractGeneratorCommand;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputOption;

/**
 * Command to generate a new Eloquent model class.
 * This command creates a new model class in the specified location (app, theme, plugin, or module).
 */
class MakeModelCommand extends AbstractGeneratorCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'pollora:make-model';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new Eloquent model class';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Model';

    /**
     * The subpath where the class should be generated.
     *
     * @var string
     */
    protected string $subPath = 'Models';

    /**
     * Get the stub file for the generator.
     */
    protected function getStub(): string
    {
        if ($this->option('pivot')) {
            return $this->resolveStubPath('/stubs/model.pivot.stub');
        }

        return $this->resolveStubPath('/stubs/model.stub');
    }

    /**
     * Resolve the fully-qualified path to the stub.
     */
    protected function resolveStubPath(string $stub): string
    {
        return file_exists($customPath = $this->laravel->basePath(trim($stub, '/')))
            ? $customPath
            : __DIR__.$stub;
    }

    /**
     * Replace the namespace for the given stub.
     */
    protected function replaceNamespace(&$stub, $name): static
    {
        // Call parent to handle standard namespace replacement
        parent::replaceNamespace($stub, $name);

        // Apply our custom replacements
        $stub = $this->replaceFillable($stub);
        $stub = $this->replaceModel($stub, $name);

        return $this;
    }

    /**
     * Replace the fillable for the given stub.
     */
    protected function replaceFillable(string $stub): string
    {
        $fillable = $this->option('fillable');

        if (! is_null($fillable)) {
            $fillable = collect(explode(',', $fillable))
                ->map(fn ($item) => "'".trim($item)."'")
                ->implode(', ');

            $fillable = "[{$fillable}]";
        } else {
            $fillable = '[]';
        }

        return str_replace(['{{ fillable }}', '{{fillable}}'], $fillable, $stub);
    }

    /**
     * Replace the model for the given stub.
     */
    protected function replaceModel(string $stub, string $name): string
    {
        $model = class_basename($name);
        $table = Str::snake(Str::pluralStudly($model));

        $stub = str_replace(['{{ model }}', '{{model}}'], $model, $stub);
        $stub = str_replace(['{{ table }}', '{{table}}'], $table, $stub);

        return $stub;
    }

    /**
     * Get the console command options.
     */
    protected function getOptions(): array
    {
        return array_merge(
            parent::getOptions(),
            [
                ['fillable', 'f', InputOption::VALUE_OPTIONAL, 'The fillable attributes'],
                ['pivot', 'p', InputOption::VALUE_NONE, 'Indicates if the generated model should be a custom intermediate table model'],
            ]
        );
    }
} 