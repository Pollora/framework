<?php

declare(strict_types=1);

namespace Pollora\TemplateHierarchy\Domain\Exceptions;

use Exception;

/**
 * Exception thrown when no template is found in the hierarchy.
 */
class TemplateNotFoundException extends Exception
{
    /**
     * Create a new template not found exception.
     */
    public function __construct(string $message = 'No template found in the hierarchy.', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
