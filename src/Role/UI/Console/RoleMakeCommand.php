<?php

declare(strict_types=1);

namespace Pollora\Role\UI\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Support\Str;
use Pollora\Console\AbstractGeneratorCommand;
use Symfony\Component\Console\Input\InputArgument;

#[Description('Create a new WordPress role class')]
class RoleMakeCommand extends AbstractGeneratorCommand
{
    protected $name = 'pollora:make:role';

    protected $type = 'Role';

    protected string $subPath = 'Cms/Roles';

    protected function getStub(): string
    {
        return __DIR__.'/stubs/role.stub';
    }

    protected function getArguments(): array
    {
        return [
            ['name', InputArgument::REQUIRED, 'The name of the role class'],
        ];
    }

    protected function replaceClass($stub, $name): string
    {
        $stub = parent::replaceClass($stub, $name);
        $className = class_basename($name);

        return str_replace(
            ['DummySlug', 'DummyLabel'],
            [Str::snake($className), Str::headline($className)],
            $stub
        );
    }
}
