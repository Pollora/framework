<?php

declare(strict_types=1);

namespace Pollora\Modules\Domain\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Thrown when a component of a module cannot be initialised and the application is in debug mode.
 */
final class ModuleComponentException extends RuntimeException
{
    /**
     * @param  string  $componentClass  The component that failed
     * @param  string  $moduleId  The module it belongs to
     * @param  Throwable  $previous  Why it failed
     */
    public static function initializationFailed(string $componentClass, string $moduleId, Throwable $previous): self
    {
        return new self(
            sprintf('Failed to initialize component %s for module %s: ', $componentClass, $moduleId).$previous->getMessage(),
            0,
            $previous,
        );
    }
}
