<?php

declare(strict_types=1);

namespace Pollora\WordPress\Exceptions;

use RuntimeException;

/**
 * Thrown when a WordPress query runs before WordPress is loaded.
 */
final class WordPressNotLoadedException extends RuntimeException
{
    public static function coreFunctionsMissing(): self
    {
        return new self('The WordPress core functions are not available. Ensure WordPress is loaded: run the query after the "init" action, or boot WordPress first.');
    }
}
