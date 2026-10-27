<?php

declare(strict_types=1);

namespace Pollora\Modules\Domain\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Thrown when a module cannot be switched on or off, or its state cannot be kept.
 */
final class ModuleStateException extends RuntimeException
{
    /**
     * A refusal whose message the caller has already worded (and translated).
     */
    public static function because(string $message, ?Throwable $previous = null): self
    {
        return new self($message, 0, $previous);
    }

    public static function comesFromConfiguration(): self
    {
        return new self('Module states come from the configuration: change config/modules.php or MODULES_ENABLED / MODULES_DISABLED.');
    }

    /**
     * @param  string  $option  The WordPress option that keeps the states
     */
    public static function cannotWriteOption(string $option, Throwable $previous): self
    {
        return new self(
            sprintf('Cannot write the module states to the %s option: %s', $option, $previous->getMessage()),
            0,
            $previous,
        );
    }

    /**
     * @param  string  $path  The JSON file that keeps the states
     */
    public static function cannotWriteFile(string $path): self
    {
        return new self(sprintf('Cannot write the module states to %s. Check that the file and its folder are writable by the web server.', $path));
    }
}
