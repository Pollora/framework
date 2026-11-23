<?php

declare(strict_types=1);

namespace Pollora\Support\Facades;

use Illuminate\Support\Facades\Facade;
use Pollora\Logging\Domain\Contracts\WordPressErrorReporterInterface;

/**
 * WordPress Error Facade
 *
 * Provides a convenient Laravel facade for logging WordPress errors.
 * This facade simplifies the usage of the WordPress error logging system
 * by providing static methods that delegate to the underlying service.
 *
 * @method static void handleDoingItWrong(string $function, string $message, string $version)
 * @method static void handleDeprecatedFunction(string $function, string $replacement, string $version)
 * @method static void handleDeprecatedArgument(string $function, string $message, string $version)
 * @method static bool disableTriggerError()
 *
 * @see WordPressErrorReporterInterface
 */
class WordPressError extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return WordPressErrorReporterInterface::class;
    }
}
