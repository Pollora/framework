<?php

declare(strict_types=1);

namespace Pollora\Modules\Domain\Exceptions;

use RuntimeException;

/**
 * A theme, plugin or module cannot be scaffolded from its template.
 */
final class ModuleScaffoldException extends RuntimeException
{
    public static function cannotCreateWorkDirectory(string $path): self
    {
        return new self(sprintf(
            'Unable to create the working directory "%s" to unpack the template. Check that the system temporary directory is writable.',
            $path,
        ));
    }
}
