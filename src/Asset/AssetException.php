<?php

declare(strict_types=1);

namespace Pollora\Asset;

use Exception;

class AssetException extends Exception
{
    /**
     * Create a new AssetException instance.
     *
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        string $message = '',
        /**
         * The context data for the exception.
         */
        protected array $context = [],
        int $code = 0,
        ?Exception $previous = null
    ) {}

    /**
     * Get the exception's context information.
     *
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->context;
    }
}
