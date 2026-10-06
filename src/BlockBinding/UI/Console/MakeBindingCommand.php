<?php

declare(strict_types=1);

namespace Pollora\BlockBinding\UI\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Support\Str;
use Pollora\Console\AbstractGeneratorCommand;
use Symfony\Component\Console\Input\InputArgument;

#[Description('Create a new Block Bindings source class')]
class MakeBindingCommand extends AbstractGeneratorCommand
{
    protected $name = 'pollora:make:binding';

    protected $type = 'Block binding';

    protected string $subPath = 'Cms/Bindings';

    protected function getStub(): string
    {
        return __DIR__.'/stubs/binding.stub';
    }

    protected function getArguments(): array
    {
        return [
            ['name', InputArgument::REQUIRED, 'The name of the binding source class'],
        ];
    }

    protected function replaceClass($stub, $name): string
    {
        $stub = parent::replaceClass($stub, $name);
        $baseName = (string) preg_replace('/Binding$/', '', class_basename($name));

        return str_replace(
            ['DummySourceName', 'DummyLabel'],
            [$this->sourceNamespace().'/'.Str::kebab($baseName), Str::headline($baseName)],
            $stub
        );
    }

    /**
     * The namespace of the source name: the theme, plugin or module, else `app`
     * (never the application name, which may be the framework's `pollora`).
     */
    private function sourceNamespace(): string
    {
        $location = $this->resolveTargetLocation();
        $namespace = $location['type'] === 'app' ? '' : Str::slug($location['name'] ?? '');

        return $namespace === '' ? 'app' : $namespace;
    }
}
