<?php

declare(strict_types=1);

namespace Pollora\Modules\Domain\Exceptions;

use Exception;

class ModuleException extends Exception
{
    public static function notFound(string $name): static
    {
        return new static(sprintf('Module [%s] not found.', $name));
    }

    public static function alreadyEnabled(string $name): static
    {
        return new static(sprintf('Module [%s] is already enabled.', $name));
    }

    public static function alreadyDisabled(string $name): static
    {
        return new static(sprintf('Module [%s] is already disabled.', $name));
    }

    public static function cannotEnable(string $name, string $reason = ''): static
    {
        $message = sprintf('Cannot enable module [%s]', $name);
        if ($reason !== '' && $reason !== '0') {
            $message .= ': '.$reason;
        }

        return new static($message);
    }

    public static function cannotDisable(string $name, string $reason = ''): static
    {
        $message = sprintf('Cannot disable module [%s]', $name);
        if ($reason !== '' && $reason !== '0') {
            $message .= ': '.$reason;
        }

        return new static($message);
    }
}
