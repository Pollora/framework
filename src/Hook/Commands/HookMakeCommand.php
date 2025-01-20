<?php

namespace Pollora\Hook\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(name: 'make:hook')]
class HookMakeCommand extends GeneratorCommand
{
    protected $name = 'make:hook';

    protected $description = 'Create a new hookable class';

    protected $type = 'Hook';

    protected function getStub()
    {
        return $this->resolveStubPath('/stubs/hook.stub');
    }

    protected function resolveStubPath($stub)
    {
        return file_exists($customPath = $this->laravel->basePath(trim($stub, '/')))
            ? $customPath
            : __DIR__.$stub;
    }

    protected function getDefaultNamespace($rootNamespace)
    {
        return $rootNamespace.'\Hooks';
    }

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

    protected function getOptions()
    {
        return [
            ['force', 'f', InputOption::VALUE_NONE, 'Create the class even if the hook already exists'],
            ['hook', null, InputOption::VALUE_OPTIONAL, 'The WordPress hook to use', 'init'],
            ['priority', null, InputOption::VALUE_OPTIONAL, 'The hook priority', 10],
        ];
    }

    public function handle()
    {
        $result = parent::handle();

        if ($result === false) {
            return $result;
        }

        // Add the hook to the hooks.php bootstrap file
        $hookClass = $this->qualifyClass($this->getNameInput());
        $bootstrapPath = $this->laravel->basePath('bootstrap/hooks.php');
        
        $content = file_get_contents($bootstrapPath);
        
        // Vérifier si le fichier est vide ou ne contient que la structure de base
        if (empty($content) || preg_match('/return\s*\[\s*\];/', $content)) {
            $content = "<?php\ndeclare(strict_types=1);\n\nreturn [\n    \\{$hookClass}::class,\n];\n";
        } else {
            // Insérer la nouvelle classe avant le dernier crochet
            $content = preg_replace(
                '/(\];)$/',
                "    \\{$hookClass}::class,\n$1",
                $content
            );
        }
        
        file_put_contents($bootstrapPath, $content);

        return $result;
    }
}
