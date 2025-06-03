<?php

declare(strict_types=1);

namespace Pollora\Console\Domain\Shared\Traits;

use Symfony\Component\Console\Input\InputArgument;

trait HasModulePathSupport
{
    protected static function getModulePathArgDefinition(): array
    {
        return [
          'module',
          InputArgument::OPTIONAL,
          'The module name in which the generated files will be created if you\'re looking to generate the files in a specific module'
        ];
    }
}
