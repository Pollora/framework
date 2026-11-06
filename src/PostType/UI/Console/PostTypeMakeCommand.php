<?php

declare(strict_types=1);

namespace Pollora\PostType\UI\Console;

use Illuminate\Console\Attributes\Aliases;
use Illuminate\Console\Attributes\Description;
use Pollora\Attributes\PostType;
use Pollora\Console\AbstractGeneratorCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

/**
 * Artisan command to generate a new custom post type class.
 *
 * This command creates a new PHP class decorated with the #[PostType] attribute
 * and other PHP attributes for WordPress custom post type registration.
 *
 * `--single-view`, `--archive-view` and `--with-views` also create the
 * single-{slug} and archive-{slug} Blade views in the theme given with `--theme`,
 * or in the active theme. The template hierarchy picks them up with no route.
 */
#[Description('Create a new WordPress custom post type class')]
#[Aliases(['pollora:make-posttype'])]
class PostTypeMakeCommand extends AbstractGeneratorCommand
{
    /**
     * Views directory, relative to the theme root.
     */
    private const string VIEWS_DIRECTORY = 'resources/views';

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'pollora:make:post-type';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'PostType';

    /**
     * The subpath where the class should be generated.
     */
    protected string $subPath = 'Cms/PostTypes';

    /**
     * Execute the console command.
     */
    public function handle(): ?bool
    {
        if (parent::handle() === false) {
            return false;
        }

        $withViews = (bool) $this->option('with-views');

        $templates = array_keys(array_filter([
            'single' => $withViews || $this->option('single-view'),
            'archive' => $withViews || $this->option('archive-view'),
        ]));

        if ($templates !== []) {
            $this->createViews($templates);
        }

        return null;
    }

    /**
     * Create a {template}-{slug}.blade.php view per template in the given or active theme.
     *
     * @param  list<string>  $templates
     */
    protected function createViews(array $templates): void
    {
        $theme = ($this->hasThemeOption() ? $this->resolveTheme() : null) ?? $this->getActiveTheme();

        if (! $theme) {
            $this->components->error('No active theme found, views not created.');

            return;
        }

        // Same slug discovery registers the post type under, so WordPress picks the views up
        $slug = (new PostType)->resolveSlug($this->qualifyClass($this->getNameInput()));

        $viewsPath = $this->getThemePath($theme).'/'.self::VIEWS_DIRECTORY;

        foreach ($templates as $template) {
            $path = $viewsPath.'/'.$template.'-'.$slug.'.blade.php';

            if ($this->files->exists($path)) {
                $this->components->warn(sprintf('View [%s] already exists.', $path));

                continue;
            }

            $this->makeDirectory($path);
            $this->files->put($path, $this->files->get(__DIR__.'/stubs/'.$template.'.blade.php.stub'));

            $this->components->success(sprintf('View [%s] created successfully.', $path));
        }
    }

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
     * Get the console command options.
     */
    protected function getOptions(): array
    {
        return [
            ...parent::getOptions(),
            ['single-view', null, InputOption::VALUE_NONE, 'Also create a single-{slug}.blade.php view in the theme'],
            ['archive-view', null, InputOption::VALUE_NONE, 'Also create an archive-{slug}.blade.php view in the theme'],
            ['with-views', null, InputOption::VALUE_NONE, 'Also create both the single and archive views in the theme'],
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
