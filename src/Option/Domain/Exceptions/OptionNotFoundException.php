<?php

declare(strict_types=1);

namespace Pollora\Option\Domain\Exceptions;

use Exception;

/**
 * Exception thrown when a required option is not found.
 */
final class OptionNotFoundException extends Exception
{
    public function __construct(string $key)
    {
        parent::__construct("Option '{$key}' not found");
    }
}
