<?php

declare(strict_types=1);

namespace Pollora\Modules\Domain\Exceptions;

use RuntimeException;

/**
 * A module's state is forced by `modules.locked` and cannot be switched.
 */
final class ModuleLockedException extends RuntimeException
{
    public static function for(string $module, bool $lockedState): self
    {
        return new self(sprintf(
            'Module "%s" is locked %s by modules.locked in config/modules.php.',
            $module,
            $lockedState ? 'enabled' : 'disabled',
        ));
    }
}
