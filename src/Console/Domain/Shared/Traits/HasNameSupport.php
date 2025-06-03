<?php

declare(strict_types=1);

namespace Pollora\Console\Domain\Shared\Traits;

use Symfony\Component\Console\Input\InputArgument;

trait HasNameSupport
{
    protected static function getNameArgDefinition(?string $desc = 'The object name'): array
    {
        return [
            'name',
            InputArgument::REQUIRED,
            $desc
        ];
    }
}
