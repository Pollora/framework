<?php

declare(strict_types=1);

namespace Pollora\Proxy\Exceptions;

use RuntimeException;

/**
 * Thrown when code calls a database function the WordPress proxy no longer supports.
 */
final class UnsupportedDatabaseCallException extends RuntimeException
{
    public static function mysqlConnect(): self
    {
        return new self('Using mysql_connect is deprecated and not supported. Please use mysqli_real_connect.');
    }
}
