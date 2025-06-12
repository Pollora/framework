<?php

declare(strict_types=1);

namespace Pollora\Modules\UI\Console\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use Pollora\Modules\UI\Console\Commands\Concerns\HasPathSupport;
use Pollora\Modules\UI\Console\Commands\Concerns\HasPluginSupport;
use Pollora\Modules\UI\Console\Commands\Concerns\HasThemeSupport;
use Pollora\Modules\UI\Console\Commands\Concerns\HasNameSupport;
use Pollora\Modules\UI\Console\Commands\Concerns\ResolvesLocation;
use Pollora\Theme\Domain\Contracts\ThemeDiscoveryInterface;
use Symfony\Component\Console\Input\InputOption;

class MakeModelCommand extends GeneratorCommand
{
    use HasNameSupport, HasPathSupport, HasPluginSupport, HasThemeSupport, ResolvesLocation;

    protected ThemeDiscoveryInterface $discovery;

    /**
     * The name and signature of the console command.
     */
    protected $name = 'pollora:make-model';

    /**
     * The console command description.
     */
    protected $description = 'Create a new Eloquent model class';

    /**
     * The type of class being generated.
     */
    protected $type = 'Model';

    /**
     * Execute the console command.
     */
    public function handle(): ?bool
    {
        // Get location info
        $location = $this->resolveTargetLocation();

        // Show where the file will be created
        $this->info("Creating model in {$location['type']}: {$location['path']}");

        return parent::handle();
    }

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
     * Get the destination class path.
     */
    protected function getPath($name): string
    {
        $location = $this->resolveTargetLocation();
        $className = str_replace($this->getNamespace($name).'\\', '', $name);

        return $this->getResolvedFilePath($location, $className, 'Models');
    }

    /**
     * Get the default namespace for the class.
     */
    protected function getDefaultNamespace($rootNamespace): string
    {
        $location = $this->resolveTargetLocation();

        return $this->getResolvedNamespace($location, 'Models');
    }

    /**
     * Get the root namespace for the class.
     */
    protected function rootNamespace(): string
    {
        $location = $this->resolveTargetLocation();

        return $location['namespace'];
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
            $this->getPathOptions(),
            $this->getPluginOptions(),
            $this->getThemeOptions(),
            [
                ['fillable', 'f', InputOption::VALUE_OPTIONAL, 'The fillable attributes'],
                ['pivot', 'p', InputOption::VALUE_NONE, 'Indicates if the generated model should be a custom intermediate table model'],
            ]
        );
    }

    /**
     * Create the directory if it doesn't exist.
     */
    protected function makeDirectory($path): string
    {
        if (! $this->files->isDirectory(dirname($path))) {
            $this->files->makeDirectory(dirname($path), 0755, true, true);
        }

        return $path;
    }
}
