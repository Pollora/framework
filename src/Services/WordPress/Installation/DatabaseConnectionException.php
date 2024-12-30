<?php

declare(strict_types=1);

namespace Pollora\Services\WordPress\Installation;

/**
 * Exception thrown when database connection fails.
 *
 * This exception is used to handle database connection errors during
 * WordPress installation or configuration.
 *
 * @extends \RuntimeException
 */
class DatabaseConnectionException extends \RuntimeException
{
    /**
     * Create exception from PDO error message.
     *
     * @param string $message The PDO error message
     * @return self
     */
    public static function fromPdoError(string $message): self
    {
        return new self("Database connection failed: $message");
    }
}
