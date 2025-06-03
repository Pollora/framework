<?php

declare(strict_types=1);

namespace Pollora\Modules\UI\Console;

use Illuminate\Console\GeneratorCommand;

class ModuleMakeCommand extends GeneratorCommand //todo : extend the existing module make command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'pollora:make-module';

    protected function getStub(): string
    {
        return '';
    }
    
}
