<?php

declare(strict_types=1);

namespace Pollora\Application\Application\Services;

use Pollora\Application\Domain\Contracts\DebugDetectorInterface;

/**
 * Application service for working with debug mode.
 */
class DebugService
{
    /**
     * Constructor.
     *
     * @param  DebugDetectorInterface  $debugDetector  The debug detector implementation
     */
    public function __construct(private readonly DebugDetectorInterface $debugDetector) {}

    /**
     * Check if the application is in debug mode.
     *
     * @return bool True if application is in debug mode
     */
    public function isDebugMode(): bool
    {
        return $this->debugDetector->isDebugMode();
    }
}
