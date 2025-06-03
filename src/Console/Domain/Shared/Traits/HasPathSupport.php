<?php

declare(strict_types=1);

namespace Pollora\Console\Domain\Shared\Traits;

use Symfony\Component\Console\Input\InputArgument;

trait HasPathSupport
{
    protected static function getPathArgDefinition(): array
    {
        return [
            'path',
            InputArgument::OPTIONAL,
            'The absolute path to the folder in which the generated files will be created.'
        ];
    }
}
