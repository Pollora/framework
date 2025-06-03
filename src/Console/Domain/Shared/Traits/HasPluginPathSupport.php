<?php

declare(strict_types=1);

namespace Pollora\Console\Domain\Shared\Traits;

use Symfony\Component\Console\Input\InputArgument;

trait HasPluginPathSupport
{
    protected static function getPluginPathArgDefinition(): array
    {
        return [
            'plugin',
            InputArgument::OPTIONAL,
            'The plugin name in which the generated files will be created if you\'re looking to generate the files in a specific plugin'
        ];
    }
}
