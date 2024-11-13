<?php

declare(strict_types=1);

namespace Pollora\Services\WordPress\Installation;

class DatabaseConnectionException extends \RuntimeException
{
    public static function fromPdoError(string $message): self
    {
        return new self("Database connection failed: $message");
    }
}
