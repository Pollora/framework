<?php

namespace Pollora\Logging\Domain\Models;

enum WordPressErrorType: string
{
    case DOING_IT_WRONG = 'doing_it_wrong';
    case DEPRECATED_FUNCTION = 'deprecated_function';
    case DEPRECATED_ARGUMENT = 'deprecated_argument';

    public function getLogLevel(): string
    {
        return match ($this) {
            self::DOING_IT_WRONG => 'warning',
            self::DEPRECATED_FUNCTION, self::DEPRECATED_ARGUMENT => 'info',
        };
    }

    public function getLogMessage(string $function): string
    {
        return match ($this) {
            self::DOING_IT_WRONG => "WordPress: {$function} called incorrectly",
            self::DEPRECATED_FUNCTION => "WordPress: Deprecated function {$function} used",
            self::DEPRECATED_ARGUMENT => "WordPress: Deprecated argument in {$function}",
        };
    }
}