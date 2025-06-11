<?php

declare(strict_types=1);

namespace Pollora\Modules\Domain\Exceptions;

use Exception;

class ModuleException extends Exception
{
    public static function notFound(string $name): static
    {
        return new static("Module [{$name}] not found.");
    }

    public static function alreadyEnabled(string $name): static
    {
        return new static("Module [{$name}] is already enabled.");
    }

    public static function alreadyDisabled(string $name): static
    {
        return new static("Module [{$name}] is already disabled.");
    }

    public static function cannotEnable(string $name, string $reason = ''): static
    {
        $message = "Cannot enable module [{$name}]";
        if ($reason) {
            $message .= ": {$reason}";
        }

        return new static($message);
    }

    public static function cannotDisable(string $name, string $reason = ''): static
    {
        $message = "Cannot disable module [{$name}]";
        if ($reason) {
            $message .= ": {$reason}";
        }

        return new static($message);
    }
}
