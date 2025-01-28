<?php

namespace Pollora\Hook\Commands;

use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

/**
 * Class HookMakeCommand
 *
 * Command to create a new hookable class.
 */
#[AsCommand(name: 'make:hook')]
class HookMakeCommand extends GeneratorCommand
{
    /**
     * The name of the console command.
     *
     * @var string
     */
    protected $name = 'make:hook';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new hookable class';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Hook';

    use HookBootstrap;

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub()
    {
        return $this->resolveStubPath('/stubs/hook.stub');
    }

    /**
     * Resolve the fully-qualified path to the stub.
     *
     * @param string $stub
     * @return string
     */
    protected function resolveStubPath($stub)
    {
        return file_exists($customPath = $this->laravel->basePath(trim($stub, '/')))
            ? $customPath
            : __DIR__.$stub;
    }

    /**
     * Get the default namespace for the class.
     *
     * @param string $rootNamespace
     * @return string
     */
    protected function getDefaultNamespace($rootNamespace)
    {
        return $rootNamespace.'\Hooks';
    }

    /**
     * Build the class with the given name.
     *
     * @param string $name
     * @return string
     */
    protected function buildClass($name)
    {
        $stub = parent::buildClass($name);

        $hook = $this->option('hook') ?? 'init';
        $priority = $this->option('priority') ?? 10;

        return str_replace(
            ['{{ hook }}', '{{ priority }}'],
            [$hook, $priority],
            $stub
        );
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
        $result = parent::handle();

        if ($result === false) {
            return $result;
        }

        // Add the hook to the hooks.php bootstrap file
        $hookClass = $this->qualifyClass($this->getNameInput());

        $this->addHookToBootstrap($hookClass);
    }
}
