<?php

declare(strict_types=1);

namespace Pollora\Option\Domain\Exceptions;

use Exception;

/**
 * Exception thrown when an option key or value is invalid.
 */
final class InvalidOptionException extends Exception
{
    public function __construct(string $message)
    {
        parent::__construct($message);
    }
}
