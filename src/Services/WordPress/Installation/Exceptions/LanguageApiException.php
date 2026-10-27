<?php

declare(strict_types=1);

namespace Pollora\Services\WordPress\Installation\Exceptions;

use RuntimeException;

/**
 * Thrown when the list of WordPress languages cannot be read from the translations API.
 */
final class LanguageApiException extends RuntimeException
{
    public static function unreachable(): self
    {
        return new self('Failed to fetch languages from WordPress API. Check the network connection and try again.');
    }

    public static function invalidResponse(): self
    {
        return new self('Invalid JSON response from WordPress API. Try again; if it persists the translations API may be down.');
    }
}
