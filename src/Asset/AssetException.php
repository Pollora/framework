<?php
declare(strict_types=1);

namespace Pollen\Asset;

use Exception;

class AssetException extends Exception
{
    /**
     * The context data for the exception.
     *
     * @var array<string, mixed>
     */
    protected array $context;

    /**
     * Create a new AssetException instance.
     *
     * @param string $message
     * @param array<string, mixed> $context
     * @param int $code
     * @param Exception|null $previous
     */
    public function __construct(string $message = "", array $context = [], int $code = 0, Exception $previous = null)
    {
       // parent::__construct($message, $code, $previous);

        $this->context = $context;
    }

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
