<?php

declare(strict_types=1);

namespace Pollora\Theme\Domain\Exceptions;

use Exception;

/**
 * Custom exception class for theme-related errors.
 *
 * Provides additional context information for theme processing errors
 * to help with debugging and error handling.
 */
class ThemeException extends Exception
{
    public static function notFound(string $name): self
    {
        return new self("Theme '{$name}' not found.");
    }

    public static function missingRequiredFiles(string $themeName, array $files): self
    {
        $filesList = implode(', ', $files);
        return new self("Theme '{$themeName}' is missing required files: {$filesList}");
    }
}
