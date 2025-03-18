<?php

declare(strict_types=1);

namespace Pollora\Asset;

use Exception;

/**
 * Custom exception class for asset-related errors.
 *
 * Provides additional context information for asset processing errors
 * to help with debugging and error handling.
 */
class AssetException extends Exception
{
    /**
     * Additional context data for debugging.
     *
     * @var array<string, mixed>
     */
    protected array $context;

    /**
     * Create a new AssetException instance.
     *
     * @param  string  $message  The exception message
     * @param  array<string, mixed>  $context  Additional context data
     * @param  int  $code  The exception code
     * @param  Exception|null  $previous  The previous throwable
     */
    public function __construct(
        string $message = '',
        array $context = [],
        int $code = 0,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }

    /**
     * Get the exception's context information.
     *
     * @return array<string, mixed> The context data
     */
    public function context(): array
    {
        return $this->context;
    }
}
